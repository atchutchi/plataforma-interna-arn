<?php
/**
 * Unidade orgânica.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Organization;

use InvalidArgumentException;

/**
 * O identificador é estável e distinto da sigla. Duas unidades podem partilhar a sigla.
 */
final readonly class Unit {

	/**
	 * Cria a unidade validando o identificador e a coerência da relação superior.
	 *
	 * @param string       $id            Identificador estável, por exemplo DRE-DGE.
	 * @param string       $acronym       Sigla tal como apresentada.
	 * @param string|null  $name          Designação extensa, ou null quando o anexo não a indica.
	 * @param UnitType     $type          Tipo da unidade.
	 * @param string|null  $parent_id     Identificador da unidade superior, quando conhecida.
	 * @param ParentStatus $parent_status Estado de validação dessa relação.
	 * @param bool         $active        Unidade ativa.
	 *
	 * @throws InvalidArgumentException Quando o identificador ou a relação superior são incoerentes.
	 */
	public function __construct(
		public string $id,
		public string $acronym,
		public ?string $name,
		public UnitType $type,
		public ?string $parent_id = null,
		public ParentStatus $parent_status = ParentStatus::Pendente,
		public bool $active = true,
	) {
		if ( 1 !== preg_match( '/^[A-Z0-9][A-Z0-9-]{0,39}$/', $id ) ) {
			throw new InvalidArgumentException( sprintf( 'Identificador de unidade inválido: "%s".', $id ) );
		}

		if ( '' === trim( $acronym ) ) {
			throw new InvalidArgumentException( sprintf( 'A unidade %s precisa de sigla.', $id ) );
		}

		if ( null !== $name && '' === trim( $name ) ) {
			throw new InvalidArgumentException( sprintf( 'A designação da unidade %s não pode ser vazia; usar null quando é desconhecida.', $id ) );
		}

		if ( $parent_id === $id ) {
			throw new InvalidArgumentException( sprintf( 'A unidade %s não pode ser superior de si própria.', $id ) );
		}

		$has_parent = null !== $parent_id;

		if ( $has_parent && ! in_array( $parent_status, array( ParentStatus::Confirmada, ParentStatus::Documental ), true ) ) {
			throw new InvalidArgumentException( sprintf( 'A unidade %s indica superior; o estado tem de ser confirmada ou documental.', $id ) );
		}

		if ( ! $has_parent && ! in_array( $parent_status, array( ParentStatus::Raiz, ParentStatus::Pendente ), true ) ) {
			throw new InvalidArgumentException( sprintf( 'A unidade %s não indica superior; o estado tem de ser raiz ou pendente.', $id ) );
		}
	}

	/**
	 * A designação extensa ainda não foi fornecida.
	 */
	public function nameIsPending(): bool {
		return null === $this->name;
	}

	/**
	 * A posição no organograma ainda não pode ser apresentada como definitiva.
	 */
	public function placementIsPending(): bool {
		return ParentStatus::Pendente === $this->parent_status || ParentStatus::Documental === $this->parent_status;
	}

	/**
	 * Devolve uma cópia com outra relação superior, mantendo o identificador.
	 *
	 * @param string|null  $parent_id Novo superior, ou null.
	 * @param ParentStatus $status    Estado da nova relação.
	 */
	public function withParent( ?string $parent_id, ParentStatus $status ): self {
		return new self( $this->id, $this->acronym, $this->name, $this->type, $parent_id, $status, $this->active );
	}

	/**
	 * Devolve uma cópia desativada. O identificador e as relações mantêm-se para histórico.
	 */
	public function deactivated(): self {
		return new self( $this->id, $this->acronym, $this->name, $this->type, $this->parent_id, $this->parent_status, false );
	}
}
