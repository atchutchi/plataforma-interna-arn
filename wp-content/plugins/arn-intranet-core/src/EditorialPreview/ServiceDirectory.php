<?php
/** Read-only service links derived from the existing navigation authorities.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\EditorialPreview;

use Arn\Intranet\People\PeopleNavigation;
use Arn\Intranet\F112\Navigation;
use Arn\Intranet\Tasks\Navigation as TaskNavigation;
use Closure;
use Throwable;

// phpcs:disable Squiz.Commenting -- Projection only. Existing authorities decide eligibility.
final class ServiceDirectory {
	private const CATALOG = array(
		'directory'      => array( 'Funcionários', 'Encontra os contactos profissionais disponíveis no directório.' ),
		'profile'        => array( 'Meu perfil', 'Consulta e actualiza os dados do teu perfil permitidos no portal.' ),
		'team'           => array( 'A minha equipa', 'Consulta os contactos da equipa que podes acompanhar.' ),
		'communications' => array( 'Comunicados', 'Lê as comunicações publicadas para ti e consulta os avisos internos.' ),
		'calendar'       => array( 'Calendário', 'Consulta os eventos disponíveis para ti e recupera o histórico.' ),
		'tasks_own'      => array( 'Minhas tarefas', 'Consulta o trabalho que te está atribuído e actualiza o progresso.' ),
		'tasks_team'     => array( 'Tarefas da equipa', 'Atribui e acompanha o trabalho na unidade que podes gerir.' ),
		'requests_own'   => array( 'Meus pedidos', 'Prepara pedidos internos e acompanha as respostas e o histórico.' ),
		'requests_team'  => array( 'Tratar pedidos', 'Analisa os pedidos submetidos nas unidades que podes gerir.' ),
		'tickets_own'    => array( 'Meus tickets', 'Abre um pedido de suporte e acompanha as respostas da equipa técnica.' ),
		'tickets_team'   => array( 'Suporte TI', 'Assume os tickets autorizados, responde e regista a solução.' ),
		'leave_own'      => array( 'Minhas férias', 'Consulta o saldo e acompanha os teus pedidos de férias.' ),
		'leave_team'     => array( 'Gerir férias', 'Analisa pedidos de férias e consulta as ausências autorizadas.' ),
	);
	private readonly Closure $people;
	private readonly Closure $communications;
	private readonly Closure $tasks;
	private readonly Closure $requests;
	private readonly Closure $tickets;
	private readonly Closure $leave;

	public function __construct( ?Closure $people = null, ?Closure $communications = null, ?Closure $tasks = null, ?Closure $requests = null, ?Closure $tickets = null, ?Closure $leave = null ) {
		$this->people         = $people ?? PeopleNavigation::destinations( ... );
		$this->communications = $communications ?? Navigation::destinations( ... );
		$this->tasks          = $tasks ?? TaskNavigation::destinations( ... );
		$this->requests       = $requests ?? \Arn\Intranet\Requests\Navigation::destinations( ... );
		$this->tickets        = $tickets ?? \Arn\Intranet\Support\Navigation::destinations( ... );
		$this->leave          = $leave ?? \Arn\Intranet\Leave\Navigation::destinations( ... );
	}

	/** No capability, account data, private content or arbitrary labels reach the view. */
	public function items(): array {
		$links = array();
		foreach ( array( $this->people, $this->communications, $this->tasks, $this->requests, $this->tickets, $this->leave ) as $source ) {
			try {
				$items = $source();
				if ( ! is_array( $items ) ) {
					continue;
				}
				foreach ( $items as $id => $item ) {
					if ( isset( self::CATALOG[ $id ] ) && is_array( $item ) && self::safeUrl( $item['url'] ?? null ) ) {
						$links[ $id ] = $item['url'];
					}
				}
			} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Uma fonte indisponível não revela detalhes nem cria acessos.
				// Other independently authorised destinations remain usable.
			}
		}
		$result = array();
		foreach ( self::CATALOG as $id => $copy ) {
			if ( isset( $links[ $id ] ) ) {
				$result[] = array(
					'id'          => $id,
					'title'       => $copy[0],
					'description' => $copy[1],
					'url'         => $links[ $id ],
				);
			}
		}
		return $result;
	}

	private static function safeUrl( mixed $url ): bool {
		if ( ! is_string( $url ) || '' === $url || preg_match( '/[\x00-\x20\x7f]/', $url ) ) {
			return false;
		}
		if ( str_starts_with( $url, '/' ) ) {
			return ! str_starts_with( $url, '//' );
		}
		return false !== filter_var( $url, FILTER_VALIDATE_URL ) && in_array( parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Validação pura, sem dependência de WordPress.
	}
}
