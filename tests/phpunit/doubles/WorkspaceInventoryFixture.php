<?php
/**
 * Fonte Workspace exclusivamente fictícia.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests\Doubles;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Fixture pura prepara ficheiros e permissões sem WordPress.

/**
 * Gera o contrato de exportação sem pessoas ou emails reais.
 */
final class WorkspaceInventoryFixture {
	/**
	 * Restringe só a pasta temporária nova do teste.
	 *
	 * @param string $path Pasta própria.
	 * @throws \RuntimeException Quando a validação ou a leitura protegida falha.
	 */
	public static function protect( string $path ): void {
		if ( 'Windows' !== PHP_OS_FAMILY ) {
			chmod( $path, 0700 );
			return; }
		$script                        = '$ErrorActionPreference="Stop"; $env:PSModulePath=$PSHOME+"\Modules"; Import-Module Microsoft.PowerShell.Security -ErrorAction Stop; $path=$env:ARN_WORKSPACE_TEST_ACL; if($path -notlike ($env:TEMP+"*arn_workspace_*")){exit 1}; $acl=Get-Acl -LiteralPath $path; $acl.SetAccessRuleProtection($true,$false); foreach($sid in @([System.Security.Principal.WindowsIdentity]::GetCurrent().User.Value,"S-1-5-18","S-1-5-32-544")){ $rule=[System.Security.AccessControl.FileSystemAccessRule]::new([System.Security.Principal.SecurityIdentifier]::new($sid),"FullControl","ContainerInherit,ObjectInherit","None","Allow"); $acl.AddAccessRule($rule) }; Set-Acl -LiteralPath $path -AclObject $acl';
		$env                           = getenv();
		$env['ARN_WORKSPACE_TEST_ACL'] = $path;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- ACL aplicada apenas a pasta temporária fictícia do teste.
		$process = proc_open(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Formato UTF-16LE próprio de EncodedCommand, sem dados pessoais ou credenciais.
			array( 'powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-EncodedCommand', base64_encode( mb_convert_encoding( $script, 'UTF-16LE', 'UTF-8' ) ) ),
			array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ),
			$pipes,
			null,
			$env,
			array(
				'bypass_shell'     => true,
				'create_no_window' => true,
			)
		);
		if ( ! is_resource( $process ) ) {
			throw new \RuntimeException( 'ACL fictícia indisponível.' ); }
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$errors = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		if ( 0 !== proc_close( $process ) ) {
			throw new \RuntimeException( 'ACL fictícia indisponível.' ); }
	}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param int $selected Contas nominativas fictícias a gerar.
	 * @return array{inventory:array, source:array, source_hash:string, inventory_hash:string} Lote fictício.
	 */
	public static function make( int $selected = 73 ): array {
		$source = array( 'users' => array() );
		$users  = array();
		for ( $index = 0; $index < $selected + 4; ++$index ) {
			$name              = 'Pessoa Fictícia ' . $index;
			$email             = 'fixture.' . $index . '@example.invalid';
			$source['users'][] = array(
				'First Name [Required]'    => 'Pessoa',
				'Last Name [Required]'     => 'Fictícia ' . $index,
				'Email Address [Required]' => $email,
				'Status [READ ONLY]'       => 'Active',
			);
			$users[]           = array(
				'display_name'        => $name,
				'primary_email'       => $email,
				'workspace_status'    => 'Active',
				'classification'      => $index < $selected ? 'nominal_selected_for_pre_registration' : 'generic_pending_review',
				'application_account' => 'not_provisioned_by_this_inventory',
			);
		}
		$source_hash = hash( 'sha256', json_encode( $source, JSON_THROW_ON_ERROR ) );
		$inventory   = array(
			'observed_at_utc' => '2030-01-15T10:00:00.5848104Z',
			'source_sha256'   => $source_hash,
			'count'           => count( $users ),
			'users'           => $users,
		);
		return array(
			'source'         => $source,
			'inventory'      => $inventory,
			'source_hash'    => $source_hash,
			'inventory_hash' => hash( 'sha256', json_encode( $inventory, JSON_THROW_ON_ERROR ) ),
		);
	}
}
