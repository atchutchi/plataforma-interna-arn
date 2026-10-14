<?php
/** Ensaio WordPress exclusivo da entrega de ficheiros privados.
 *
 * @package ArnIntranet
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Documents\PrivateFiles\FileRecord;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Documents\PrivateFiles\LocalFileInstaller;
use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Identity\IdentityWpdb;

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo de CLI administradora.' );
}
$checks = 0;
$assert = static function ( bool $value, string $label ) use ( &$checks ): void {
	if ( ! $value ) {
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$prefix = 'arn_lpf_' . bin2hex( random_bytes( 5 ) ) . '_';
$root   = sys_get_temp_dir() . '/arn-lpf-' . bin2hex( random_bytes( 6 ) );
mkdir( $root );
$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$database->set_prefix( $prefix );
$database->suppress_errors( true );
$schema   = new FileSchema( $database );
$registry = new WpdbFileRegistry( $database );
$source   = null;
try {
	$store         = LocalPrivateFiles::store( $root );
	$content       = ( new ReflectionClass( LocalFileInstaller::class ) )->getMethod( 'content' );
	$ids           = array();
	$expected      = array();
	$local_install = LocalFileInstaller::eligible(
		wp_get_environment_type(),
		defined( 'ARN_ALLOW_LOCAL_PRIVATE_FILES' ) ? ARN_ALLOW_LOCAL_PRIVATE_FILES : null,
		defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
		defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null,
		true,
		true
	);
	if ( $local_install ) {
		$owner = get_user_by( 'login', LocalFixtureUser::LOGIN );
		$assert( $owner instanceof WP_User && '1' === get_user_meta( $owner->ID, LocalFixtureUser::META_KEY, true ), 'Ana marcada existente' );
		$initial_entries = glob( $root . '/*' );
		$assert( array() === ( false === $initial_entries ? array() : $initial_entries ), 'pasta privada exclusiva inicialmente vazia' );
		$assert( '0' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (%s,%s,%s)', $schema->table( 'files' ), $schema->table( 'audit' ), $schema->table( 'schema' ) ) ), 'tabelas exclusivas inicialmente ausentes' );
		$installer = new LocalFileInstaller( $prefix, $root );
		$ids       = $installer->install( $owner->ID );
		$assert( array( 'own', 'unit-a', 'unit-b' ) === array_keys( $ids ), 'instalador criou três objectos' );
	} else {
		$schema->install();
	}
	foreach ( array(
		'own'    => 1,
		'unit-a' => 2,
		'unit-b' => 3,
	) as $key => $object_id ) {
		foreach ( array(
			'original'  => 2,
			'thumbnail' => 2,
			'preview'   => 2,
			'old'       => 1,
		) as $name => $version ) {
			$variant = 'old' === $name ? 'original' : $name;
			$bytes   = $content->invoke( null, $object_id, $version, $variant );
			if ( $local_install ) {
				$record = $registry->findForObject( 'local_document', $object_id, $version, $variant );
				$assert( $record instanceof FileRecord && $record->blob->id === $ids[ $key ][ $name ], 'tuple inicial do instalador ' . $key . ' ' . $name );
				$expected[ $record->blob->id ] = $bytes;
			} else {
				$source = tempnam( sys_get_temp_dir(), 'arn-lpf-src-' );
				file_put_contents( $source, $bytes );
				$blob = $store->importFile( $source );
				unlink( $source );
				$source = null;
				$registry->register( new FileRecord( $blob, 'local_document', $object_id, $version, $variant, get_current_user_id(), time() ) );
				$ids[ $key ][ $name ]  = $blob->id;
				$expected[ $blob->id ] = $bytes;
			}
		}
	}
	$assert( 12 === count( $expected ), 'doze blobs reais registados' );
	if ( $local_install ) {
		$assert( '12' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM %i', $schema->table( 'files' ) ) ), 'instalação inicial criou doze refs' );
		$assert( '12' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM %i', $schema->table( 'audit' ) ) ), 'instalação inicial criou doze eventos' );
		$assert( $ids === $installer->install( $owner->ID ), 'instalação repetida é idempotente' );
		$assert( $ids === $installer->install( $owner->ID ), 'segunda instalação não cria refs' );
		$assert( '12' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM %i', $schema->table( 'audit' ) ) ), 'instalação idempotente não cria eventos' );
		$wrong_owner = false;
		try {
			$installer->install( get_current_user_id() );
		} catch ( RuntimeException ) {
			$wrong_owner = true;
		}
		$assert( $wrong_owner, 'administrador não pode ser owner' );
	} else {
		$installer_denied = false;
		try {
			( new LocalFileInstaller( $prefix, $root ) )->install( 1 );
		} catch ( RuntimeException ) {
			$installer_denied = true;
		}
		$assert( $installer_denied, 'instalador recusa ambiente ou flags inactivos' );
	}
	$session     = array(
		'arn_authz_local'    => true,
		'arn_authz_user_id'  => 7,
		'arn_authz_revision' => 3,
	);
	$snapshot    = static fn( array $grants, AccountState $state = AccountState::Active ): AuthorizationSnapshot => new AuthorizationSnapshot( 7, $state, 3, array_map( static fn( Grant $grant, int $index ): StoredGrant => new StoredGrant( $index + 1, $grant ), $grants, array_keys( $grants ) ) );
	$own         = new Grant( 7, Role::Employee, Scope::own(), 0, null );
	$reader      = static fn(): array => array( $snapshot( array( $own ) ), 7, $session );
	$service     = new LocalPrivateFiles( $reader, static fn() => $registry, static fn() => $store, static fn(): bool => true );
	$visible_own = $service->listVisible();
	$assert( array( 'own' ) === array_keys( $visible_own ) && 3 === count( $visible_own['own'] ), 'lista apenas variantes próprias correntes' );
	foreach ( array( 'original', 'thumbnail', 'preview' ) as $variant ) {
		$opened = $service->open( $ids['own'][ $variant ] );
		$assert( null !== $opened && hash_equals( $expected[ $ids['own'][ $variant ] ], stream_get_contents( $opened['handle'] ) ), 'conteúdo real próprio ' . $variant );
		fclose( $opened['handle'] );
	}
	foreach ( array( $ids['own']['old'], $ids['unit-a']['original'], $ids['unit-b']['preview'], str_repeat( 'f', 32 ), array( $ids['own']['original'] ) ) as $denied ) {
		$assert( null === $service->open( $denied ), 'versão, âmbito ou ID recusado' );
	}
	$a         = new Grant( 7, Role::Employee, Scope::units( array( 'unidade-ficticia-a' ) ), 0, null );
	$reader_a  = static fn(): array => array( $snapshot( array( $a ) ), 7, $session );
	$service_a = new LocalPrivateFiles( $reader_a, static fn() => $registry, static fn() => $store, static fn(): bool => true );
	$visible_a = $service_a->listVisible();
	$assert( array( 'unit-a' ) === array_keys( $visible_a ) && 3 === count( $visible_a['unit-a'] ) && hash_equals( $ids['unit-a']['original'], $visible_a['unit-a']['original']->blob->id ), 'lista da unidade A filtrada por Employee' );
	$opened = $service_a->open( $ids['unit-a']['preview'] );
	$assert( null !== $opened && hash_equals( $expected[ $ids['unit-a']['preview'] ], stream_get_contents( $opened['handle'] ) ), 'employee unidade A pode ler JPEG' );
	fclose( $opened['handle'] );
	$assert( null === $service_a->open( $ids['unit-b']['preview'] ), 'unidade B recusada' );
	$reader_old  = static fn(): array => array( $snapshot( array( $own ) ), 7, array_replace( $session, array( 'arn_authz_revision' => 2 ) ) );
	$service_old = new LocalPrivateFiles( $reader_old, static fn() => $registry, static fn() => $store, static fn(): bool => true );
	$assert( null === $service_old->open( $ids['own']['original'] ), 'sessão antiga recusada' );
	$reader_suspended  = static fn(): array => array( $snapshot( array( $own ), AccountState::Suspended ), 7, $session );
	$service_suspended = new LocalPrivateFiles( $reader_suspended, static fn() => $registry, static fn() => $store, static fn(): bool => true );
	$assert( null === $service_suspended->open( $ids['own']['original'] ), 'conta suspensa recusada' );
	$assert( '12' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM %i', $schema->table( 'audit' ) ) ), 'doze eventos auditados' );
	if ( isset( $installer ) ) {
		file_put_contents( $root . '/' . $ids['own']['preview'], 'adulterado' );
		$corrupt = false;
		try {
			$installer->install( $owner->ID );
		} catch ( RuntimeException ) {
			$corrupt = true;
		}
		$assert( $corrupt, 'referência adulterada impede reinstalação' );
	}
	echo esc_html( 'Entrega local privada: ' . $checks . " verificações.\n" );
} finally {
	if ( null !== $source && is_file( $source ) ) {
		unlink( $source );
	}
	foreach ( array( 'audit', 'files', 'schema' ) as $table ) {
		$database->query( $database->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( $table ) ) );
	}
	$entries = glob( $root . '/*' );
	foreach ( false === $entries ? array() : $entries as $blob_path ) {
		unlink( $blob_path );
	}
	rmdir( $root );
}
