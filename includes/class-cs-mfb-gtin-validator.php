<?php
/**
 * GTIN validator: digit count + GS1 check digit algorithm.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_GTIN_Validator {

	/** Valid GTIN digit lengths. */
	const VALID_LENGTHS = array( 8, 12, 13, 14 );

	/**
	 * Validate a GTIN string.
	 *
	 * @param string $gtin Numeric string (non-numeric chars already stripped).
	 * @return array { valid: bool, digit_count: int, expected: int[], check_digit_valid: bool, error: string }
	 */
	public static function validate( $gtin ) {
		$gtin = preg_replace( '/[^0-9]/', '', (string) $gtin );
		$len  = strlen( $gtin );

		if ( $len === 0 ) {
			return array(
				'valid'             => false,
				'digit_count'       => 0,
				'expected'          => self::VALID_LENGTHS,
				'check_digit_valid' => false,
				'error'             => 'empty',
			);
		}

		if ( ! in_array( $len, self::VALID_LENGTHS, true ) ) {
			return array(
				'valid'             => false,
				'digit_count'       => $len,
				'expected'          => self::VALID_LENGTHS,
				'check_digit_valid' => false,
				'error'             => 'wrong_length',
			);
		}

		$check_ok = self::gs1_check_digit( $gtin );

		return array(
			'valid'             => $check_ok,
			'digit_count'       => $len,
			'expected'          => self::VALID_LENGTHS,
			'check_digit_valid' => $check_ok,
			'error'             => $check_ok ? '' : 'invalid_check_digit',
		);
	}

	/**
	 * Validate the GS1 check digit.
	 *
	 * Pads the GTIN to 14 digits, multiplies alternating digits by 3 and 1,
	 * sums the products, and verifies the check digit brings the total to a
	 * multiple of 10.
	 *
	 * @param string $gtin Numeric string (8/12/13/14 digits).
	 * @return bool
	 */
	public static function gs1_check_digit( $gtin ) {
		$gtin   = str_pad( $gtin, 14, '0', STR_PAD_LEFT );
		$digits = str_split( $gtin );
		$sum    = 0;

		for ( $i = 0; $i < 13; $i++ ) {
			$weight = ( $i % 2 === 0 ) ? 3 : 1;
			$sum   += (int) $digits[ $i ] * $weight;
		}

		$check = ( 10 - ( $sum % 10 ) ) % 10;

		return $check === (int) $digits[13];
	}
}
