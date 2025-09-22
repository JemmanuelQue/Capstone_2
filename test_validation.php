<?php
function validateSSS($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    return strlen($cleaned) == 10 && ctype_digit($cleaned);
}

function validateTIN($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    return (strlen($cleaned) == 9 || strlen($cleaned) == 12) && ctype_digit($cleaned);
}

function validatePhilHealth($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    return strlen($cleaned) == 12 && ctype_digit($cleaned);
}

function validatePagIbig($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    return strlen($cleaned) == 12 && ctype_digit($cleaned);
}

function isPlaceholderGovtId($idValue) {
    if (empty($idValue)) {
        return true;
    }
    
    $cleaned = preg_replace('/\D/', '', $idValue);
    
    // Check if it's just "0" or all zeros
    return $cleaned === '0' || (strlen($cleaned) > 0 && str_repeat('0', strlen($cleaned)) === $cleaned);
}

echo "Testing placeholder detection:\n";
echo 'isPlaceholderGovtId(""): ' . (isPlaceholderGovtId('') ? 'true' : 'false') . "\n";
echo 'isPlaceholderGovtId("0"): ' . (isPlaceholderGovtId('0') ? 'true' : 'false') . "\n";
echo 'isPlaceholderGovtId("0000000000"): ' . (isPlaceholderGovtId('0000000000') ? 'true' : 'false') . "\n";
echo 'isPlaceholderGovtId("000000000"): ' . (isPlaceholderGovtId('000000000') ? 'true' : 'false') . "\n";
echo 'isPlaceholderGovtId("000000000000"): ' . (isPlaceholderGovtId('000000000000') ? 'true' : 'false') . "\n";
echo 'isPlaceholderGovtId("1234567890"): ' . (isPlaceholderGovtId('1234567890') ? 'true' : 'false') . "\n";
echo 'isPlaceholderGovtId("123456789"): ' . (isPlaceholderGovtId('123456789') ? 'true' : 'false') . "\n";

echo "\nTesting new logic:\n";
echo 'validateSSS("0") || isPlaceholderGovtId("0"): ' . (validateSSS('0') || isPlaceholderGovtId('0') ? 'PASS' : 'FAIL') . "\n";
echo 'validateSSS("0000000000") || isPlaceholderGovtId("0000000000"): ' . (validateSSS('0000000000') || isPlaceholderGovtId('0000000000') ? 'PASS (placeholder skip)' : 'FAIL') . "\n";
echo 'validateSSS("1234567890") || isPlaceholderGovtId("1234567890"): ' . (validateSSS('1234567890') || isPlaceholderGovtId('1234567890') ? 'PASS (valid)' : 'FAIL') . "\n";
echo 'validateSSS("123") || isPlaceholderGovtId("123"): ' . (validateSSS('123') || isPlaceholderGovtId('123') ? 'PASS' : 'FAIL (should fail)') . "\n";
?>
