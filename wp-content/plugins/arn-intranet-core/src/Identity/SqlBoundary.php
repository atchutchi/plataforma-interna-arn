<?php
/**
 * Classifica o SQL efectivamente apresentado à ligação dedicada.
 *
 * Subconjunto suportado dentro de uma transacção controlada:
 * uma única instrução DML (SELECT, INSERT, UPDATE, DELETE, REPLACE, WITH,
 * SHOW, DO, DESCRIBE, EXPLAIN), CREATE/DROP TEMPORARY TABLE, e as fronteiras
 * explícitas START TRANSACTION / BEGIN [WORK], COMMIT/ROLLBACK [WORK]
 * [AND CHAIN]. SET de variáveis de utilizador e de sessão que não alteram
 * autocommit, completion_type ou TRANSACTION. Comentários de linha, bloco e
 * executáveis só entram se todas as interpretações motor/versão forem
 * seguras. Fora deste subconjunto a instrução é recusada; uma lista de
 * palavras proibidas não é prova de segurança.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

/**
 * Analisa tokens, literais e comentários sem executar o SQL.
 */
final class SqlBoundary {

	/**
	 * Classifica uma instrução já filtrada pelos hooks query.
	 *
	 * @param string $sql         SQL final.
	 * @param string $server_info VERSION() ou server_info.
	 * @return array{trusted:bool,command:string,implicit_commit:bool,autocommit:bool,completion_type:bool,savepoint:bool,release:bool,chain:bool}
	 */
	public static function classify( string $sql, string $server_info ): array {
		$empty  = array(
			'trusted'         => false,
			'command'         => 'other',
			'implicit_commit' => false,
			'autocommit'      => false,
			'completion_type' => false,
			'savepoint'       => false,
			'release'         => false,
			'chain'           => false,
		);
		$tokens = self::tokenize( $sql );
		if ( null === $tokens ) {
			return $empty;
		}
		$is_mariadb = str_contains( strtoupper( $server_info ), 'MARIADB' );
		$variants   = self::interpretations( $tokens, $is_mariadb );
		if ( array() === $variants ) {
			return $empty;
		}
		$merged = null;
		foreach ( $variants as $variant ) {
			$classified = self::classifyTokens( $variant );
			if ( null === $merged ) {
				$merged = $classified;
				continue;
			}
			if ( $merged['command'] !== $classified['command'] || $merged['chain'] !== $classified['chain'] || $merged['release'] !== $classified['release'] ) {
				$merged['trusted'] = false;
			}
			$merged['trusted']         = $merged['trusted'] && $classified['trusted'];
			$merged['implicit_commit'] = $merged['implicit_commit'] || $classified['implicit_commit'];
			$merged['autocommit']      = $merged['autocommit'] || $classified['autocommit'];
			$merged['completion_type'] = $merged['completion_type'] || $classified['completion_type'];
			$merged['savepoint']       = $merged['savepoint'] || $classified['savepoint'];
			$merged['release']         = $merged['release'] || $classified['release'];
			$merged['chain']           = $merged['chain'] || $classified['chain'];
		}
		return $merged ?? $empty;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string,version?:int,dialect?:string,inner?:array}> $tokens Tokens.
	 * @param bool                                                                                 $is_mariadb Motor MariaDB.
	 * @return array<int,array<int,array{type:string,value:string}>>
	 */
	private static function interpretations( array $tokens, bool $is_mariadb ): array {
		$expanded = array();
		foreach ( $tokens as $token ) {
			if ( 'exec' !== $token['type'] ) {
				$expanded[] = $token;
				continue;
			}
			if ( 'mariadb' === ( $token['dialect'] ?? '' ) && ! $is_mariadb ) {
				continue;
			}
			$expanded[] = $token;
		}
		$indexes = array();
		foreach ( $expanded as $index => $token ) {
			if ( 'exec' === $token['type'] ) {
				$indexes[] = $index;
			}
		}
		if ( count( $indexes ) > 4 ) {
			return array();
		}
		$variants = array( array() );
		foreach ( $expanded as $token ) {
			$next = array();
			if ( 'exec' !== $token['type'] ) {
				foreach ( $variants as $variant ) {
					$variant[] = $token;
					$next[]    = $variant;
				}
				$variants = $next;
				continue;
			}
			foreach ( $variants as $variant ) {
				$next[] = $variant;
				$active = $variant;
				foreach ( $token['inner'] ?? array() as $inner ) {
					$active[] = $inner;
				}
				$next[] = $active;
			}
			$variants = $next;
		}
		return $variants;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $tokens Tokens sem comentários executáveis.
	 * @return array{trusted:bool,command:string,implicit_commit:bool,autocommit:bool,completion_type:bool,savepoint:bool,release:bool,chain:bool}
	 */
	private static function classifyTokens( array $tokens ): array {
		$result = array(
			'trusted'         => true,
			'command'         => 'other',
			'implicit_commit' => false,
			'autocommit'      => false,
			'completion_type' => false,
			'savepoint'       => false,
			'release'         => false,
			'chain'           => false,
		);
		// Rotinas DDL podem conter pontos e vírgulas no próprio corpo. Só se
		// executam fora da transacção controlada, nunca como DML admissível.
		if ( isset( $tokens[0] ) && 'word' === $tokens[0]['type'] && self::isDdlVerb( $tokens[0]['value'] ) && ! self::isTemporaryTable( $tokens ) ) {
			$result['implicit_commit'] = true;
			return $result;
		}
		$statements = self::splitStatements( $tokens );
		if ( count( $statements ) > 1 ) {
			$result['trusted'] = false;
			return $result;
		}
		$words = $statements[0] ?? array();
		if ( array() === $words ) {
			return $result;
		}
		$first = $words[0]['value'];
		if ( self::isTemporaryTable( $words ) ) {
			return $result;
		}
		if ( self::isTransactionBegin( $words ) ) {
			$result['command'] = 'begin';
			return $result;
		}
		if ( self::isCommitOrRollback( $words, $result ) ) {
			return $result;
		}
		if ( 'SET' === $first ) {
			return self::classifySet( $words, $result );
		}
		if ( self::isSavepointCommand( $words ) ) {
			$result['savepoint'] = true;
			return $result;
		}
		if ( self::isCompound( $words ) ) {
			$result['trusted'] = false;
			return $result;
		}
		if ( self::isSafeDml( $words ) ) {
			if ( self::containsBareDdlVerb( $words ) ) {
				$result['implicit_commit'] = true;
			}
			return $result;
		}
		if ( self::isDdlVerb( $first ) || self::containsBareDdlVerb( $words ) ) {
			$result['implicit_commit'] = true;
			return $result;
		}
		$result['trusted'] = false;
		return $result;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens de uma instrução.
	 */
	private static function isTemporaryTable( array $words ): bool {
		return isset( $words[0], $words[1], $words[2] )
			&& in_array( $words[0]['value'], array( 'CREATE', 'DROP' ), true )
			&& 'TEMPORARY' === $words[1]['value']
			&& 'TABLE' === $words[2]['value'];
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 */
	private static function isTransactionBegin( array $words ): bool {
		if ( 'START' === $words[0]['value'] ) {
			if ( ! isset( $words[1] ) || 'TRANSACTION' !== $words[1]['value'] ) {
				return false;
			}
			$rest = array_slice( $words, 2 );
			foreach ( $rest as $token ) {
				if ( ! in_array( $token['value'], array( 'WITH', 'CONSISTENT', 'SNAPSHOT', 'READ', 'WRITE', 'ONLY', ',', 'AND' ), true ) ) {
					return false;
				}
			}
			return true;
		}
		if ( 'BEGIN' !== $words[0]['value'] ) {
			return false;
		}
		$rest = array_slice( $words, 1 );
		if ( isset( $rest[0] ) && 'WORK' === $rest[0]['value'] ) {
			array_shift( $rest );
		}
		if ( array() === $rest ) {
			return true;
		}
		return self::isChainTail( $rest );
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 * @param array                                      $result Classificação mutável.
	 */
	private static function isCommitOrRollback( array $words, array &$result ): bool {
		$first = $words[0]['value'];
		if ( ! in_array( $first, array( 'COMMIT', 'ROLLBACK' ), true ) ) {
			return false;
		}
		$rest = array_slice( $words, 1 );
		if ( isset( $rest[0] ) && 'WORK' === $rest[0]['value'] ) {
			array_shift( $rest );
		}
		if ( isset( $rest[0] ) && 'TO' === $rest[0]['value'] ) {
			$result['savepoint'] = true;
			$result['command']   = 'other';
			return true;
		}
		if ( self::isChainTail( $rest ) ) {
			$result['command'] = strtolower( $first );
			$result['chain']   = self::hasChain( $rest );
			$result['release'] = self::hasRelease( $rest );
			return true;
		}
		$result['trusted'] = false;
		$result['command'] = strtolower( $first );
		return true;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 */
	private static function isChainTail( array $words ): bool {
		if ( array() === $words ) {
			return true;
		}
		$allowed = array( 'AND', 'NO', 'CHAIN', 'RELEASE' );
		foreach ( $words as $token ) {
			if ( ! in_array( $token['value'], $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 */
	private static function hasChain( array $words ): bool {
		foreach ( $words as $index => $token ) {
			if ( 'CHAIN' === $token['value'] && $index > 0 && 'AND' === $words[ $index - 1 ]['value'] ) {
				return ! ( $index > 1 && 'NO' === $words[ $index - 2 ]['value'] );
			}
		}
		return false;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 */
	private static function hasRelease( array $words ): bool {
		foreach ( $words as $token ) {
			if ( 'RELEASE' === $token['value'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 */
	private static function isSavepointCommand( array $words ): bool {
		return in_array( $words[0]['value'], array( 'SAVEPOINT', 'RELEASE' ), true );
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 */
	private static function isCompound( array $words ): bool {
		$first = $words[0]['value'];
		if ( in_array( $first, array( 'IF', 'CASE', 'WHILE', 'LOOP', 'REPEAT', 'DECLARE' ), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 */
	private static function isSafeDml( array $words ): bool {
		return in_array( $words[0]['value'], array( 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'WITH', 'SHOW', 'DO', 'DESCRIBE', 'EXPLAIN', 'HANDLER', 'KILL' ), true );
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param string $word Palavra.
	 */
	private static function isDdlVerb( string $word ): bool {
		return in_array(
			$word,
			array( 'ALTER', 'CREATE', 'DROP', 'RENAME', 'TRUNCATE', 'GRANT', 'REVOKE', 'INSTALL', 'UNINSTALL', 'LOCK', 'UNLOCK', 'FLUSH', 'RESET', 'ANALYZE', 'CHECK', 'OPTIMIZE', 'REPAIR', 'CACHE', 'LOAD', 'CHANGE', 'XA', 'CALL', 'PREPARE', 'EXECUTE', 'DEALLOCATE' ),
			true
		);
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 */
	private static function containsBareDdlVerb( array $words ): bool {
		foreach ( $words as $index => $token ) {
			if ( 0 === $index ) {
				continue;
			}
			if ( 'word' === $token['type'] && self::isDdlVerb( $token['value'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $words Tokens.
	 * @param array                                      $result Classificação.
	 * @return array
	 */
	private static function classifySet( array $words, array $result ): array {
		// O âmbito pertence à decisão, não apenas ao nome da variável.
		$scope = $words[1]['value'] ?? '';
		if ( in_array( $scope, array( 'GLOBAL', 'PERSIST', 'PERSIST_ONLY' ), true )
			|| ( in_array( $scope, array( 'SESSION', 'LOCAL' ), true ) && 'TRANSACTION' === ( $words[2]['value'] ?? '' ) ) ) {
			$result['trusted'] = false;
			return $result;
		}
		if ( isset( $words[1] ) && 'PASSWORD' === $words[1]['value'] ) {
			$result['trusted']         = false;
			$result['implicit_commit'] = true;
			return $result;
		}
		if ( isset( $words[1] ) && 'STATEMENT' === $words[1]['value'] ) {
			$result['trusted']         = false;
			$result['implicit_commit'] = true;
			return $result;
		}
		if ( isset( $words[1] ) && 'TRANSACTION' === $words[1]['value'] ) {
			$allowed = array( 'TRANSACTION', 'ISOLATION', 'LEVEL', 'READ', 'COMMITTED', 'UNCOMMITTED', 'REPEATABLE', 'SERIALIZABLE' );
			foreach ( array_slice( $words, 1 ) as $token ) {
				if ( ! in_array( $token['value'], $allowed, true ) ) {
					$result['trusted'] = false;
					return $result;
				}
			}
			return $result;
		}
		if ( isset( $words[1] ) && in_array( $words[1]['value'], array( 'NAMES', 'CHARACTER', 'CHARSET' ), true ) ) {
			// SET NAMES accepts only its isolated form, including WordPress COLLATE.
			// A trailing SET option can otherwise alter session or global variables.
			$isolated = 3 === count( $words ) || ( 5 === count( $words ) && 'word' === $words[3]['type'] && 'COLLATE' === $words[3]['value'] && in_array( $words[4]['type'], array( 'word', 'ident', 'string' ), true ) && 1 === preg_match( '/\A[A-Z0-9_]+\z/D', strtoupper( $words[4]['value'] ) ) );
			if ( ! $isolated || 'NAMES' !== $words[1]['value'] || ! isset( $words[2] ) || ! in_array( $words[2]['type'], array( 'word', 'ident', 'string' ), true ) || ! in_array( strtoupper( $words[2]['value'] ), array( 'UTF8', 'UTF8MB3', 'UTF8MB4' ), true ) ) {
				$result['trusted'] = false;
			}
			return $result;
		}
		$assignments = self::splitAssignments( array_slice( $words, 1 ) );
		if ( null === $assignments ) {
			$result['trusted'] = false;
			return $result;
		}
		foreach ( $assignments as $assignment ) {
			$name = self::assignmentTarget( $assignment );
			if ( null === $name ) {
				$result['trusted'] = false;
				return $result;
			}
			if ( 'AUTOCOMMIT' === $name ) {
				$result['autocommit'] = true;
			}
			if ( 'COMPLETION_TYPE' === $name ) {
				$result['completion_type'] = true;
			}
			// Características transaccionais usam SET TRANSACTION sem âmbito;
			// atribuições de variáveis (incluindo aliases MariaDB) não são admitidas.
			if ( in_array( $name, array( 'SQL_MODE', 'CHARACTER_SET_CLIENT', 'CHARACTER_SET_CONNECTION', 'COLLATION_CONNECTION', 'TRANSACTION_ISOLATION', 'TX_ISOLATION', 'TRANSACTION_READ_ONLY', 'TX_READ_ONLY' ), true ) ) {
				$result['trusted'] = false;
			}
		}
		return $result;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $tokens Tokens após SET.
	 * @return array<int,array<int,array{type:string,value:string}>>|null
	 */
	private static function splitAssignments( array $tokens ): ?array {
		$groups  = array();
		$current = array();
		foreach ( $tokens as $token ) {
			if ( ',' === $token['value'] && 'punct' === $token['type'] ) {
				if ( array() === $current ) {
					return null;
				}
				$groups[] = $current;
				$current  = array();
				continue;
			}
			$current[] = $token;
		}
		if ( array() !== $current ) {
			$groups[] = $current;
		}
		return $groups;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $assignment Lado esquerdo e valor.
	 */
	private static function assignmentTarget( array $assignment ): ?string {
		$lhs = array();
		foreach ( $assignment as $token ) {
			if ( in_array( $token['value'], array( '=', ':=' ), true ) ) {
				break;
			}
			$lhs[] = $token;
		}
		if ( array() === $lhs ) {
			return null;
		}
		$parts = array();
		foreach ( $lhs as $token ) {
			if ( in_array( $token['type'], array( 'word', 'ident' ), true ) ) {
				$parts[] = $token['value'];
			} elseif ( '.' !== $token['value'] ) {
				return null;
			}
		}
		if ( str_starts_with( implode( '', $parts ), '@' ) && ! str_starts_with( implode( '', $parts ), '@@' ) ) {
			return '';
		}
		// Um prefixo separado não introduz um segmento vazio antes do âmbito.
		if ( '@@' === ( $parts[0] ?? '' ) ) {
			array_shift( $parts );
		}
		$joined = ltrim( implode( '.', $parts ), '@' );
		if ( 1 === preg_match( '/^(?:GLOBAL|PERSIST|PERSIST_ONLY)(?:\.|$)/', $joined ) ) {
			return null;
		}
		$joined   = preg_replace( '/^(?:SESSION|LOCAL)\./', '', $joined ) ?? $joined;
		$joined   = preg_replace( '/^(?:SESSION|LOCAL)$/', '', $joined ) ?? $joined;
		$segments = explode( '.', $joined );
		return (string) end( $segments );
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param array<int,array{type:string,value:string}> $tokens Tokens.
	 * @return array<int,array<int,array{type:string,value:string}>>
	 */
	private static function splitStatements( array $tokens ): array {
		$statements = array();
		$current    = array();
		foreach ( $tokens as $token ) {
			if ( ';' === $token['value'] && 'punct' === $token['type'] ) {
				if ( array() !== $current ) {
					$statements[] = $current;
					$current      = array();
				}
				continue;
			}
			$current[] = $token;
		}
		if ( array() !== $current ) {
			$statements[] = $current;
		}
		return $statements;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param string $sql SQL original.
	 * @return array<int,array<string,mixed>>|null
	 */
	private static function tokenize( string $sql ): ?array {
		$tokens = array();
		$length = strlen( $sql );
		$i      = 0;
		while ( $i < $length ) {
			$char = $sql[ $i ];
			if ( ctype_space( $char ) ) {
				++$i;
				continue;
			}
			if ( '\'' === $char || '"' === $char ) {
				$string = self::readQuoted( $sql, $i, $char );
				if ( null === $string ) {
					return null;
				}
				$tokens[] = array(
					'type'  => 'string',
					'value' => $string,
				);
				continue;
			}
			if ( '`' === $char ) {
				$ident = self::readQuoted( $sql, $i, '`' );
				if ( null === $ident ) {
					return null;
				}
				$tokens[] = array(
					'type'  => 'ident',
					'value' => strtoupper( $ident ),
				);
				continue;
			}
			if ( '#' === $char ) {
				$i = self::endOfLine( $sql, $i );
				continue;
			}
			if ( $i + 1 < $length && '-' === $char && '-' === $sql[ $i + 1 ] && ( $i + 2 >= $length || ctype_space( $sql[ $i + 2 ] ) ) ) {
				$i = self::endOfLine( $sql, $i );
				continue;
			}
			if ( $i + 1 < $length && '/' === $char && '*' === $sql[ $i + 1 ] ) {
				$comment = self::readBlockComment( $sql, $i );
				if ( null === $comment ) {
					return null;
				}
				if ( 'exec' === $comment['type'] ) {
					$tokens[] = $comment;
				}
				continue;
			}
			if ( 1 === preg_match( '/[A-Za-z0-9_$@]/', $char ) ) {
				$start = $i;
				++$i;
				while ( $i < $length && 1 === preg_match( '/[A-Za-z0-9_$@]/', $sql[ $i ] ) ) {
					++$i;
				}
				$tokens[] = array(
					'type'  => 'word',
					'value' => strtoupper( substr( $sql, $start, $i - $start ) ),
				);
				continue;
			}
			$tokens[] = array(
				'type'  => 'punct',
				'value' => ':' === $char && $i + 1 < $length && '=' === $sql[ $i + 1 ] ? ':=' : $char,
			);
			$i       += ':' === $char && $i + 1 < $length && '=' === $sql[ $i + 1 ] ? 2 : 1;
		}
		return $tokens;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param string $sql   SQL.
	 * @param int    $index Índice do delimitador, avançado.
	 * @param string $quote Delimitador.
	 */
	private static function readQuoted( string $sql, int &$index, string $quote ): ?string {
		$length = strlen( $sql );
		++$index;
		$buffer = '';
		while ( $index < $length ) {
			$char = $sql[ $index ];
			if ( '\\' === $char && $index + 1 < $length && '`' !== $quote ) {
				$buffer .= $sql[ $index + 1 ];
				$index  += 2;
				continue;
			}
			if ( $char === $quote ) {
				if ( $index + 1 < $length && $sql[ $index + 1 ] === $quote ) {
					$buffer .= $quote;
					$index  += 2;
					continue;
				}
				++$index;
				return $buffer;
			}
			$buffer .= $char;
			++$index;
		}
		return null;
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param string $sql   SQL.
	 * @param int    $index Início de /*.
	 * @return array<string,mixed>|null
	 */
	private static function readBlockComment( string $sql, int &$index ): ?array {
		$length = strlen( $sql );
		if ( $index + 2 < $length && '!' === $sql[ $index + 2 ] ) {
			return self::readExecutableComment( $sql, $index, 'mysql' );
		}
		if ( $index + 3 < $length && 'M' === $sql[ $index + 2 ] && '!' === $sql[ $index + 3 ] ) {
			return self::readExecutableComment( $sql, $index, 'mariadb' );
		}
		$end = strpos( $sql, '*/', $index + 2 );
		if ( false === $end ) {
			return null;
		}
		$index = $end + 2;
		return array(
			'type'  => 'comment',
			'value' => '',
		);
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param string $sql     SQL.
	 * @param int    $index   Início.
	 * @param string $dialect mysql ou mariadb.
	 * @return array<string,mixed>|null
	 */
	private static function readExecutableComment( string $sql, int &$index, string $dialect ): ?array {
		$length     = strlen( $sql );
		$cursor     = $index + ( 'mysql' === $dialect ? 3 : 4 );
		$digits     = '';
		$digit_size = 0;
		while ( $cursor < $length && ctype_digit( $sql[ $cursor ] ) && $digit_size < 6 ) {
			$digits .= $sql[ $cursor ];
			++$digit_size;
			++$cursor;
		}
		$end = strpos( $sql, '*/', $cursor );
		if ( false === $end ) {
			return null;
		}
		$inner  = substr( $sql, $cursor, $end - $cursor );
		$index  = $end + 2;
		$tokens = self::tokenize( $inner );
		if ( null === $tokens ) {
			return null;
		}
		return array(
			'type'    => 'exec',
			'dialect' => $dialect,
			'version' => '' === $digits ? 0 : (int) $digits,
			'inner'   => $tokens,
			'value'   => '',
		);
	}

	/**
	 * Análise interna da instrução.
	 *
	 * @param string $sql   SQL.
	 * @param int    $index Posição.
	 */
	private static function endOfLine( string $sql, int $index ): int {
		$length = strlen( $sql );
		while ( $index < $length && "\n" !== $sql[ $index ] && "\r" !== $sql[ $index ] ) {
			++$index;
		}
		return $index;
	}
}
