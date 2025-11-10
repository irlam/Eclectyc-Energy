<?php
/**
 * Unit test for reading type normalization
 */

// Mock the normalizeReadingType logic
function normalizeReadingType(?string $value): string
{
    if ($value === null) {
        return 'actual';
    }

    $normalized = strtolower(trim($value));

    // Check for single letter codes
    if ($normalized === 'a') {
        return 'actual';
    }
    if ($normalized === 'e') {
        return 'estimated';
    }

    // Check for full words
    if (in_array($normalized, ['actual', 'estimated', 'manual'], true)) {
        return $normalized;
    }

    // Default to 'actual' for unrecognized values
    return 'actual';
}

echo "=== Unit Test: normalizeReadingType() ===\n\n";

$testCases = [
    // Input => Expected Output
    'A' => 'actual',
    'a' => 'actual',
    'E' => 'estimated',
    'e' => 'estimated',
    'actual' => 'actual',
    'Actual' => 'actual',
    'ACTUAL' => 'actual',
    'estimated' => 'estimated',
    'Estimated' => 'estimated',
    'ESTIMATED' => 'estimated',
    'manual' => 'manual',
    'Manual' => 'manual',
    'MANUAL' => 'manual',
    '' => 'actual',
    '  A  ' => 'actual',
    '  E  ' => 'estimated',
    'unknown' => 'actual', // Default
    'xyz' => 'actual', // Default
];

// Add null test separately
$nullTests = [
    null => 'actual',
];

$passed = 0;
$failed = 0;

foreach ($testCases as $input => $expected) {
    $result = normalizeReadingType($input);
    $success = $result === $expected;
    
    if ($success) {
        echo "✓ ";
        $passed++;
    } else {
        echo "✗ ";
        $failed++;
    }
    
    $inputDisplay = $input === '' ? "''" : "'$input'";
    echo "Input: " . str_pad($inputDisplay, 20) . " => Expected: '" . str_pad($expected, 10) . "' Got: '$result'\n";
}

// Test null separately
foreach ($nullTests as $input => $expected) {
    $result = normalizeReadingType($input);
    $success = $result === $expected;
    
    if ($success) {
        echo "✓ ";
        $passed++;
    } else {
        echo "✗ ";
        $failed++;
    }
    
    echo "Input: null                => Expected: '" . str_pad($expected, 10) . "' Got: '$result'\n";
}

echo "\n=== Test Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed.\n";
    exit(1);
}
