<?php
/** Synthetic expiring authority behind a real consumer and CMS row lock.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\Documents\Catalog\{DocumentAuthority, Metadata, Revision, WpdbDocumentRepository};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\{Asset, Content, NewsAuthority, Version, WpdbNewsRepository};

// phpcs:disable WordPress, Squiz.Commenting, Generic.CodeAnalysis.UnusedFunctionParameter -- Synthetic authority tests time advancing while the real SQL row is locked.
return static function ( IdentityWpdb $db, string $mode, int $id, int $expected ): void {
	$expiry = null;
	$check  = static function () use ( &$expiry ): void {
		if ( null === $expiry ) {
			$expiry = microtime( true ) + 0.3;
			echo "checked\n";
		} elseif ( microtime( true ) >= $expiry ) {
			echo "expired\n";
			throw new RuntimeException( 'Synthetic grant expired during policy wait.' );
		}
	};
	$blob   = new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'a', 64 ), 8, 'image/png' );
	try {
		if ( in_array( $mode, array( 'expire-news', 'expire-news-revise' ), true ) ) {
			$authority  = new class( $check ) implements NewsAuthority {
				public function __construct( private Closure $check ) {}
				public function assertUploadCurrent(): void {
					( $this->check )(); }
				public function lockAndAssert( int $author_id, Scope $review_scope, string $operation ): array {
					( $this->check )();
					return array( 'actor_id' => 7, 'reviewer_ids' => array( 8 ) );
				}
			};
			$content    = new Content( 'Expiração fictícia', 'Texto', 'circular', new Asset( 'featured', $blob, 'Imagem' ), array(), array() );
			$repository = new WpdbNewsRepository( $db, $authority );
			if ( 'expire-news-revise' === $mode ) {
				$before = $repository->find( $id );
				$repository->revise( $id, new Version( count( $before->versions ) + 1, $content, 7, 104 ), $expected, 'Espera pela política' );
			} else {
				$repository->create( 7, Scope::queues( array( 'editorial-ficticia' ) ), new Version( 1, $content, 7, 104 ), 'Espera pela política' );
			}
		} elseif ( in_array( $mode, array( 'expire-document', 'expire-document-revise' ), true ) ) {
			$authority  = new class( $check ) implements DocumentAuthority {
				public function __construct( private Closure $check ) {}
				public function assertUploadCurrent(): void {
					( $this->check )(); }
				public function lockAndAssert( Scope $scope, array $recipient_ids ): int {
					( $this->check )();
					return 7;
				}
				public function assertActiveRecipients( array $recipient_ids ): void {}
			};
			$metadata   = new Metadata( 'Expiração fictícia', 'Resumo', 'regulamento', 'CMS-02', 'Unidade fictícia', '2026-10-20', null, null, array( 7 ) );
			$repository = new WpdbDocumentRepository( $db, $authority );
			if ( 'expire-document-revise' === $mode ) {
				$before = $repository->find( $id );
				$repository->revise( $id, new Revision( count( $before->versions ) + 1, $metadata, $blob, 7, 104 ), $expected, 'Espera pela política' );
			} else {
				$repository->create( Scope::units( array( 'CMS-TESTE' ) ), new Revision( 1, $metadata, $blob, 7, 104 ), 'Espera pela política' );
			}
		} else {
			throw new LogicException( 'Unknown worker.' );
		}
		echo "saved\n";
	} catch ( RuntimeException ) {
		echo "refused\n";
	}
};
