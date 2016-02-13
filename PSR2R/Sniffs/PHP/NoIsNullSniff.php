<?php

namespace PSR2R\Sniffs\PHP;

use PHP_CodeSniffer_Tokens;

/**
 */
class NoIsNullSniff extends \PSR2R\Tools\AbstractSniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return [T_STRING];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param \PHP_CodeSniffer_File $phpcsFile All the tokens found in the document.
	 * @param int $stackPtr The position of the current token
	 *    in the stack passed in $tokens.
	 * @return void
	 */
	public function process(\PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$wrongTokens = [T_FUNCTION, T_OBJECT_OPERATOR, T_NEW, T_DOUBLE_COLON];

		$tokens = $phpcsFile->getTokens();

		$tokenContent = $tokens[$stackPtr]['content'];
		if (strtolower($tokenContent) !== 'is_null') {
			return;
		}

		$previous = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
		if (!$previous || in_array($tokens[$previous]['code'], $wrongTokens)) {
			return;
		}

		$openingBraceIndex = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
		if (!$openingBraceIndex || $tokens[$openingBraceIndex]['type'] !== 'T_OPEN_PARENTHESIS') {
			return;
		}

		$closingBraceIndex = $tokens[$openingBraceIndex]['parenthesis_closer'];

		$error = $tokenContent .'() found, should be strict === null check.';

		$possibleCastIndex = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
		$negated = false;
		if ($possibleCastIndex && $tokens[$possibleCastIndex]['code'] === T_BOOLEAN_NOT) {
			$negated = true;
		}
		// We dont want to fix double !!
		if ($negated) {
			$anotherPossibleCastIndex = $phpcsFile->findPrevious(T_WHITESPACE, ($possibleCastIndex - 1), null, true);
			if ($tokens[$anotherPossibleCastIndex]['code'] === T_BOOLEAN_NOT) {
				$phpcsFile->addError($error, $stackPtr);
				return;
			}
		}

		// We don't want to fix stuff with bad inline assignment
		if ($this->contains($phpcsFile, 'T_EQUAL', $openingBraceIndex + 1, $closingBraceIndex - 1)) {
			$phpcsFile->addError($error, $stackPtr);
			return;
		}

		$beginningIndex = $negated ? $possibleCastIndex : $stackPtr;
		$endIndex = $closingBraceIndex;

		$fix = $phpcsFile->addFixableError($error, $stackPtr);
		if ($fix) {
			$needsBrackets = $this->needsBrackets($phpcsFile, $openingBraceIndex, $closingBraceIndex);
			$leadingComparison = $this->hasLeadingComparison($phpcsFile, $beginningIndex);
			$trailingComparison = $this->hasTrailingComparison($phpcsFile, $closingBraceIndex);

			if ($leadingComparison) {
				$possibleBeginningIndex = $this->findUnnecessaryLeadingComparisonStart($phpcsFile, $beginningIndex);
				if ($possibleBeginningIndex !== null) {
					$beginningIndex = $possibleBeginningIndex;
					$leadingComparison = false;
					if ($tokens[$beginningIndex]['code'] === T_FALSE) {
						$negated = !$negated;
					}
				}
			}

			if ($trailingComparison) {
				$possibleEndIndex = $this->findUnnecessaryLeadingComparisonStart($phpcsFile, $endIndex);
				if ($possibleEndIndex !== null) {
					$endIndex = $possibleEndIndex;
					$trailingComparison = false;
					if ($tokens[$endIndex]['code'] === T_FALSE) {
						$negated = !$negated;
					}
				}
			}

			if (!$needsBrackets && ($leadingComparison || $this->leadRequiresBrackets($phpcsFile, $beginningIndex))) {
				$needsBrackets = true;
			}
			if (!$needsBrackets && $trailingComparison) {
				$needsBrackets = true;
			}

			$comparisonString = ' ' . ($negated ? '!' : '=') . '== null';

			$phpcsFile->fixer->beginChangeset();

			if ($negated) {
				//$phpcsFile->fixer->replaceToken($possibleCastIndex, '');
			}
			if ($beginningIndex !== $stackPtr) {
				for ($i = $beginningIndex; $i < $stackPtr; $i++) {
					$phpcsFile->fixer->replaceToken($i, '');
				}
			}
			if ($endIndex !== $closingBraceIndex) {
				for ($i = $endIndex; $i > $closingBraceIndex; $i--) {
					$phpcsFile->fixer->replaceToken($i, '');
				}
			}

			$phpcsFile->fixer->replaceToken($stackPtr, '');
			if (!$needsBrackets) {
				$phpcsFile->fixer->replaceToken($openingBraceIndex, '');
				$phpcsFile->fixer->replaceToken($closingBraceIndex, $comparisonString);
			} else {
				$phpcsFile->fixer->replaceToken($closingBraceIndex, $comparisonString . ')');
			}

			$phpcsFile->fixer->endChangeset();
		}
	}

	protected function leadRequiresBrackets(\PHP_CodeSniffer_File $phpcsFile, $index) {
		$tokens = $phpcsFile->getTokens();

		$previous = $phpcsFile->findPrevious(T_WHITESPACE, ($index - 1), null, true);
		if ($this->isCast($phpcsFile, $previous)) {
			return true;
		}
		if (in_array($tokens[$previous]['code'], PHP_CodeSniffer_Tokens::$arithmeticTokens)) {
			return true;
		}

		return false;
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param int $index
	 * @return bool
	 */
	protected function isCast(\PHP_CodeSniffer_File $phpcsFile, $index) {
		$tokens = $phpcsFile->getTokens();

		return in_array($index, PHP_CodeSniffer_Tokens::$castTokens);
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param int $index
	 * @return int|null
	 */
	protected function findUnnecessaryLeadingComparisonStart(\PHP_CodeSniffer_File $phpcsFile, $index) {
		$tokens = $phpcsFile->getTokens();

		$previous = $phpcsFile->findPrevious(T_WHITESPACE, ($index - 1), null, true);
		if (!in_array($tokens[$previous]['code'], [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL])) {
			return null;
		}

		$previous = $phpcsFile->findPrevious(T_WHITESPACE, ($previous - 1), null, true);
		if (!in_array($tokens[$previous]['code'], [T_TRUE, T_FALSE])) {
			return null;
		}

		return $previous;
	}

	protected function findUnnecessaryTrailingComparisonEnd(\PHP_CodeSniffer_File $phpcsFile, $index) {
		$tokens = $phpcsFile->getTokens();

		$next = $phpcsFile->findNext(T_WHITESPACE, ($index + 1), null, true);
		if (!in_array($tokens[$next]['code'], [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL])) {
			return null;
		}

		$next = $phpcsFile->findPrevious(T_WHITESPACE, ($next - 1), null, true);
		if (!in_array($tokens[$next]['code'], [T_TRUE, T_FALSE])) {
			return null;
		}

		return $next;
	}

	protected function hasLeadingComparison(\PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$previous = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
		return $this->isComparison($phpcsFile, $previous);
	}

	protected function hasTrailingComparison(\PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();

		$next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
		return $this->isComparison($phpcsFile, $next);
	}

	/**
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param int $index
	 * @return bool
	 */
	protected function isComparison(\PHP_CodeSniffer_File $phpcsFile, $index) {
		$tokens = $phpcsFile->getTokens();

		$blacklistedCodes = [
			T_IS_NOT_EQUAL, T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_GREATER_OR_EQUAL, T_IS_SMALLER_OR_EQUAL
		];
		$blacklistedTypes = [
			'T_LESS_THAN', 'T_GREATER_THAN',
		];
		if (in_array($tokens[$index]['code'], $blacklistedCodes)) {
			return true;
		}
		if (in_array($tokens[$index]['type'], $blacklistedTypes)) {
			return true;
		}

		return false;
	}

}