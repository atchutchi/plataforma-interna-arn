# Run as the same Windows account that created the production ACME order.
# No tokens, passwords or private keys belong in this file.
[CmdletBinding()]
param([switch]$CheckOnly)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$siteName = 'ARN-Intranet'
$dnsName = 'plataforma-arn.duckdns.org'
$bindingInfo = '192.168.17.205:443:plataforma-arn.duckdns.org'
$phase = 'preflight'
$lockHandle = $null

try {
    $principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Elevation required'
    }
    # Install this script in an administrator-only directory, outside the web root.
    $lockHandle = [IO.File]::Open((Join-Path $PSScriptRoot 'renew-arn.lock'), 'OpenOrCreate', 'ReadWrite', 'None')
    Import-Module WebAdministration -ErrorAction Stop
    Import-Module Posh-ACME -RequiredVersion 4.35.0 -ErrorAction Stop
    $site = Get-Website -Name $siteName
    if (-not $site -or $site.applicationPool -ne 'ARN-Intranet') { throw 'Unexpected site' }
    $bindings = @(Get-WebBinding -Name $siteName -Protocol https | Where-Object { $_.bindingInformation -eq $bindingInfo })
    if ($bindings.Count -ne 1 -or [int]$bindings[0].sslFlags -ne 1) { throw 'Unexpected binding' }
    $otherBindings = @(Get-WebBinding -Protocol https | Where-Object {
        $_.bindingInformation -ne $bindingInfo -and $_.bindingInformation -like ('*:' + $dnsName)
    })
    if ($otherBindings.Count -gt 0) { throw 'Ambiguous SNI hostname' }
    Set-PAServer LE_PROD | Out-Null
    Set-PAOrder -MainDomain $dnsName | Out-Null
    $cert = Get-PACertificate
    if (-not $cert -or @($cert.AllSANs).Count -ne 1 -or $cert.AllSANs[0] -ne $dnsName) { throw 'Unexpected certificate' }

    if (-not $CheckOnly) {
        $phase = 'renewal'
        # No Force: a daily run only renews when due. Suppress provider details.
        $null = Submit-Renewal -MainDomain $dnsName -WarningAction SilentlyContinue -Verbose:$false -Debug:$false
        # Always reconcile the current order, even after a previous deployment failure.
        $cert = Get-PACertificate
        if (-not $cert -or @($cert.AllSANs).Count -ne 1 -or $cert.AllSANs[0] -ne $dnsName) { throw 'Unexpected renewed certificate' }
    }
    if ($cert.NotAfter -le (Get-Date).AddDays(14)) { throw 'Certificate expires within 14 days' }
    $phase = 'certificate-store'
    $certPath = 'Cert:\LocalMachine\My\' + $cert.Thumbprint
    if (-not (Test-Path -LiteralPath $certPath)) {
        if ($CheckOnly) { throw 'Certificate not installed' }
        Install-PACertificate -PACertificate $cert -StoreLocation LocalMachine -StoreName My -NotExportable | Out-Null
    }
    $installed = Get-Item -LiteralPath $certPath
    if (-not $installed.HasPrivateKey -or $installed.NotBefore -gt (Get-Date) -or $installed.NotAfter -le (Get-Date)) { throw 'Invalid installed certificate' }
    if (@($installed.DnsNameList | Where-Object { $_.Unicode -eq $dnsName }).Count -ne 1) { throw 'Wrong installed SAN' }
    if (-not $installed.Verify()) { throw 'Certificate trust verification failed' }
    $phase = 'binding'
    $binding = @(Get-WebBinding -Name $siteName -Protocol https | Where-Object { $_.bindingInformation -eq $bindingInfo })
    if ($binding.Count -ne 1 -or [int]$binding[0].sslFlags -ne 1) { throw 'Binding changed' }
    if ($binding[0].certificateHash -ne $cert.Thumbprint -or $binding[0].certificateStoreName -ne 'My') {
        if ($CheckOnly) { throw 'Binding certificate mismatch' }
        $binding[0].AddSslCertificate($cert.Thumbprint, 'My')
    }
    $verified = Get-WebBinding -Name $siteName -Protocol https | Where-Object { $_.bindingInformation -eq $bindingInfo }
    if ($verified.certificateHash -ne $cert.Thumbprint) { throw 'Binding verification failed' }
    $phase = 'https'
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
    $response = Invoke-WebRequest -Uri ('https://' + $dnsName + '/teste-https.html') -UseBasicParsing -TimeoutSec 30
    if ($response.StatusCode -ne 200 -or $response.Content -notmatch 'ARN - HTTPS operacional') { throw 'HTTPS probe failed' }
    $line = '{0:o} OK CheckOnly={1} Expires={2:o} Thumbprint={3}' -f (Get-Date), [bool]$CheckOnly, $installed.NotAfter, $installed.Thumbprint
    Add-Content -LiteralPath (Join-Path $PSScriptRoot 'renew-arn.log') -Value $line
    Write-Output $line
    exit 0
}
catch {
    # Do not log raw exceptions: provider URLs may include credentials.
    $message = '{0:o} FAIL Phase={1}. Review server configuration privately.' -f (Get-Date), $phase
    if ($null -ne $lockHandle) {
        Add-Content -LiteralPath (Join-Path $PSScriptRoot 'renew-arn.log') -Value $message -ErrorAction SilentlyContinue
    }
    Write-Output $message
    exit 1
}
finally {
    if ($null -ne $lockHandle) { $lockHandle.Dispose() }
}
