<?php
/**
 * Government ID Formatter and Validator Functions
 * 
 * This file contains functions to format, validate, and process
 * Philippine government ID numbers (SSS, TIN, PhilHealth, Pag-IBIG)
 */

/**
 * Format SSS Number (XX-XXXXXXX-X)
 */
function formatSSS($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    if (strlen($cleaned) == 10) {
        return substr($cleaned, 0, 2) . '-' . substr($cleaned, 2, 7) . '-' . substr($cleaned, 9, 1);
    }
    return $number;
}

/**
 * Format TIN Number (XXX-XXX-XXX or XXX-XXX-XXX-XXX)
 */
function formatTIN($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    if (strlen($cleaned) == 9) {
        return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6, 3);
    } elseif (strlen($cleaned) == 12) {
        return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6, 3) . '-' . substr($cleaned, 9, 3);
    }
    return $number;
}

/**
 * Format PhilHealth Number (XX-XXXXXXXXX-X)
 */
function formatPhilHealth($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    if (strlen($cleaned) == 12) {
        return substr($cleaned, 0, 2) . '-' . substr($cleaned, 2, 9) . '-' . substr($cleaned, 11, 1);
    }
    return $number;
}

/**
 * Format Pag-IBIG Number (XXXX-XXXX-XXXX)
 */
function formatPagIbig($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    if (strlen($cleaned) == 12) {
        return substr($cleaned, 0, 4) . '-' . substr($cleaned, 4, 4) . '-' . substr($cleaned, 8, 4);
    }
    return $number;
}

/**
 * Remove formatting from government ID (keep only digits)
 */
function unformatGovtId($formattedId) {
    return preg_replace('/\D/', '', $formattedId);
}

/**
 * Validate SSS Number (must be 10 digits)
 */
function validateSSS($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    return strlen($cleaned) == 10 && ctype_digit($cleaned);
}

/**
 * Validate TIN Number (must be 9 or 12 digits)
 */
function validateTIN($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    return (strlen($cleaned) == 9 || strlen($cleaned) == 12) && ctype_digit($cleaned);
}

/**
 * Validate PhilHealth Number (must be 12 digits)
 */
function validatePhilHealth($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    return strlen($cleaned) == 12 && ctype_digit($cleaned);
}

/**
 * Validate Pag-IBIG Number (must be 12 digits)
 */
function validatePagIbig($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    return strlen($cleaned) == 12 && ctype_digit($cleaned);
}

