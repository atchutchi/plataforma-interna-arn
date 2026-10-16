<?php
/**
 * Alteração de estado e invalidação após o commit.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Closure;
use RuntimeException;
use WP_Session_Tokens;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Autoridade CLI injectada explicitamente, sem pedidos de browser.
/** A revisão persistida cobre uma falha de destruição posterior das sessões. */
final class PreRegistrationStateService {
	private readonly Closure $authority;
	private readonly Closure $destroy;
	public function __construct( private readonly WpdbPreRegistrationRepository $repository, ?callable $authority = null, ?callable $destroy = null ) {
		$this->authority = Closure::fromCallable( $authority ?? static fn( int $id ): bool=>WorkspacePreRegistrationCommand::permitted( true ) && get_current_user_id() === $id );
		$this->destroy   = Closure::fromCallable( $destroy ?? static fn( int $id )=>WP_Session_Tokens::get_instance( $id )->destroy_all() );}
	public function setEnabled( string $id, int $expected_revision, bool $enabled, int $actor_id, string $reason, int $now ): PreRegistration {
		if ( true !== ( $this->authority )( $actor_id ) ) {
			throw new RuntimeException( 'Pré-cadastro indisponível.' );
		}$record = $this->repository->setEnabled( $id, $expected_revision, $enabled, $actor_id, $reason, $now );
		if ( $record->revision !== $expected_revision ) {
			( $this->destroy )( $record->user_id );
		}return $record;}
}
