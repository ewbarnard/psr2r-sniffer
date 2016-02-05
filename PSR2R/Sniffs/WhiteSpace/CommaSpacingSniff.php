<?php
namespace PSR2R\Sniffs\WhiteSpace;

/**
 * Ensures no whitespaces and one whitespace is placed around each comma.
 */
class CommaSpacingSniff implements \PHP_CodeSniffer_Sniff {

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return array(T_COMMA);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param \PHP_CodeSniffer_File $phpcsFile All the tokens found in the document.
	 * @param integer $stackPtr The position of the current token
	 *    in the stack passed in $tokens.
	 * @return void
	 */
	public function process(\PHP_CodeSniffer_File $phpcsFile, $stackPtr) {
		$tokens = $phpcsFile->getTokens();

		$next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

		if ($tokens[$next]['code'] !== T_WHITESPACE && ($next !== $stackPtr + 2)) {
			// Last character in a line is ok.
			if ($tokens[$next]['line'] === $tokens[$stackPtr]['line']) {
				$error = 'Missing space after comma';
				$fix = $phpcsFile->addFixableError($error, $next);
				if ($fix) {
					$phpcsFile->fixer->addContent($stackPtr, ' ');
				}
			}
		}

		$previous = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);

		if ($tokens[$previous]['code'] !== T_WHITESPACE && ($previous !== $stackPtr - 1)) {
			$error = 'Space before comma, expected none, though';
			$fix = $phpcsFile->addFixableError($error, $next);
			if ($fix) {
				$content = $tokens[$previous]['content'];
				$phpcsFile->fixer->replaceToken($previous + 1, '');
			}
		}
	}

}