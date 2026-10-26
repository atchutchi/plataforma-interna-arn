<?php
/** Native navigation shapes must retain symbolic module keys.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\EditorialPreview\ServiceSources;
use PHPUnit\Framework\Attributes\{RunInSeparateProcess, PreserveGlobalState};
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval, Squiz.PHP.Eval.Discouraged -- Literal closed stubs only in a separate test process, without external input.
final class ServiceSourcesTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testNativePageIdsDoNotReplaceSymbolicDestinationKeys(): void {
		eval( 'namespace Arn\\Intranet\\Support; final class Navigation { public static function destinations(): array { return ["tickets_own" => ["id" => 45, "url" => "/?page_id=45", "label" => "Meus tickets"]]; } } final class Portal { public function projection(string $purpose,string $query,int $page): array { return ["total"=>1,"items"=>[["id"=>7,"title"=>"Ticket fictício","view"=>"own"]]]; } public function notices(): array { return [["id"=>2,"ticket"=>(object)["id"=>7,"title"=>"Ticket fictício"],"kind"=>"claim","created_at"=>100,"read_at"=>null,"view"=>"own"]]; } }' );
		function add_query_arg( mixed $key, mixed $value, mixed $url = null ): string {
			if ( is_array( $key ) ) {
				$url  = $value;
				$args = $key;
			} else {
				$args = array( $key => $value ); }
			return $url . '&' . http_build_query( $args );
		}
		$result = ServiceSources::search( 'tickets', 'fictício', 1 );
		self::assertNotNull( $result );
		self::assertSame( '/?page_id=45&entry=7', $result['items'][0]['url'] );
		$notices = ServiceSources::notices( 'tickets' );
		self::assertNotNull( $notices );
		self::assertSame( '/?page_id=45&notices=1', $notices['url'] );
		self::assertSame( '/?page_id=45&entry=7', $notices['items'][0]['url'] );
		self::assertSame( 100, $notices['items'][0]['created_at'] );
		self::assertNull( $notices['items'][0]['read_at'] );
	}
}
