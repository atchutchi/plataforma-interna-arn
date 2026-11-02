<?php
/** Closed evidence binding for fictitious queue receipts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Media\{ConsumptionReceipt, ConsumerJobs};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting
final class ConsumptionReceiptTest extends TestCase {
	private function state(): array {
		return array(
			'owner'   => 42,
			'context' => ConsumerJobs::intent(
				'tasks',
				7,
				3,
				'Anexo de laboratório',
				array(
					'label' => 'Fictício',
					'view'  => 'own',
				)
			),
			'items'   => array(
				array(
					'blob' => array(
						'id'     => str_repeat( 'a', 32 ),
						'sha256' => str_repeat( 'b', 64 ),
						'size'   => 12,
						'mime'   => 'application/pdf',
					),
				),
			),
		);
	}
	public function testFingerprintBindsOriginalsActorBatchAndCompleteIntent(): void {
		$s     = $this->state();
		$batch = str_repeat( 'c', 32 );
		$r     = new ConsumptionReceipt( $batch, $s );
		self::assertSame(
			$r->fingerprint,
			( new ConsumptionReceipt(
				$batch,
				$s + array(
					'revision' => 99,
					'history'  => array(),
				)
			) )->fingerprint
		);
		$variants                         = array();
		$v                                = $s;
		$v['owner']                       = 43;
		$variants[]                       = $v;
		$v                                = $s;
		$v['context']['payload']['label'] = 'Outro anexo';
		$variants[]                       = $v;
		$v                                = $s;
		$v['context']['expected']         = 4;
		$variants[]                       = $v;
		$v                                = $s;
		$v['context']['id']               = 8;
		$variants[]                       = $v;
		$v                                = $s;
		$v['items'][0]['blob']['sha256']  = str_repeat( 'd', 64 );
		$variants[]                       = $v;
		$v                                = $s;
		$v['items'][0]['blob']['id']      = str_repeat( 'e', 32 );
		$variants[]                       = $v;
		foreach ( $variants as $v ) {
			self::assertNotSame( $r->fingerprint, ( new ConsumptionReceipt( $batch, $v ) )->fingerprint ); }
		self::assertNotSame( $r->fingerprint, ( new ConsumptionReceipt( str_repeat( 'f', 32 ), $s ) )->fingerprint );
		$r->assertTarget( 'tasks', 'attach', 42, 7, 4 );
	}
	public function testTargetCannotCrossModuleActorObjectOperationOrRevision(): void {
		$r = new ConsumptionReceipt( str_repeat( 'c', 32 ), $this->state() );
		foreach ( array( array( 'tickets', 'attach', 42, 7, 4 ), array( 'tasks', 'create', 42, 7, 4 ), array( 'tasks', 'attach', 43, 7, 4 ), array( 'tasks', 'attach', 42, 8, 4 ), array( 'tasks', 'attach', 42, 7, 5 ) ) as $args ) {
			try {
				$r->assertTarget( ...$args );
			} catch ( RuntimeException ) {
				$this->addToAssertionCount( 1 );
				continue; }
			self::fail( 'Mismatched receipt accepted' );
		}
	}
}
