<?php
/** Gerações persistentes sem equivaler uma fixture a prova SQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\LocalIdentityLink;
use PHPUnit\Framework\TestCase;
/** Invariantes do valor de geração. */
final class IdentityGenerationTest extends TestCase {
	/** Verifica a fronteira da geração. */
	public function testUnpersistedLinkHasNoInstitutionalGeneration(): void {
		self::assertSame( 0, ( new LocalIdentityLink( 12, 'accounts.google.com', 'fictional', true, true ) )->generation );
	}
	/** Verifica a fronteira da geração. */
	public function testPersistedGenerationIsPreserved(): void {
		self::assertSame( 7, ( new LocalIdentityLink( 12, 'accounts.google.com', 'fictional', true, true, 7 ) )->generation );
	}
	/** Verifica a fronteira da geração. */
	public function testNegativeGenerationIsInvalid(): void {
		$this->expectException( \InvalidArgumentException::class );
		new LocalIdentityLink( 12, 'accounts.google.com', 'fictional', true, true, -1 );
	}
}
