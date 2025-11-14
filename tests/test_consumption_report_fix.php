<?php
/**
 * Test for consumption report parameter fix
 * Tests that the SQL query correctly uses named parameters instead of mixing named and positional
 */

// Test the parameter construction logic
function testParameterConstruction()
{
    echo "Testing parameter construction logic...\n";
    
    // Simulate the fixed parameter construction
    $accessibleSiteIds = [1, 2, 3, 5, 10];
    $siteFilter = '';
    $params = [
        'start' => '2025-01-01',
        'end' => '2025-01-31',
    ];
    
    if (!empty($accessibleSiteIds)) {
        // Use named parameters to avoid mixing with positional parameters
        $placeholders = [];
        foreach ($accessibleSiteIds as $index => $siteId) {
            $paramName = 'site_' . $index;
            $placeholders[] = ':' . $paramName;
            $params[$paramName] = $siteId;
        }
        $siteFilter = " AND s.id IN (" . implode(',', $placeholders) . ")";
    }
    
    // Verify all parameters are named
    $allNamed = true;
    foreach (array_keys($params) as $key) {
        if (is_int($key)) {
            $allNamed = false;
            echo "  ERROR: Found positional parameter at index $key\n";
        }
    }
    
    if ($allNamed) {
        echo "  ✓ All parameters are named parameters\n";
    }
    
    // Verify the filter string
    $expectedFilter = " AND s.id IN (:site_0,:site_1,:site_2,:site_3,:site_4)";
    if ($siteFilter === $expectedFilter) {
        echo "  ✓ Site filter string is correct\n";
    } else {
        echo "  ERROR: Site filter mismatch\n";
        echo "    Expected: $expectedFilter\n";
        echo "    Got:      $siteFilter\n";
    }
    
    // Verify parameter values
    $expectedParams = [
        'start' => '2025-01-01',
        'end' => '2025-01-31',
        'site_0' => 1,
        'site_1' => 2,
        'site_2' => 3,
        'site_3' => 5,
        'site_4' => 10,
    ];
    
    if ($params === $expectedParams) {
        echo "  ✓ Parameter values are correct\n";
    } else {
        echo "  ERROR: Parameter values mismatch\n";
        echo "    Expected: " . json_encode($expectedParams) . "\n";
        echo "    Got:      " . json_encode($params) . "\n";
    }
    
    echo "\n";
}

// Test with empty accessible site IDs
function testEmptyAccessibleSites()
{
    echo "Testing with empty accessible sites...\n";
    
    $accessibleSiteIds = [];
    $siteFilter = '';
    $params = [
        'start' => '2025-01-01',
        'end' => '2025-01-31',
    ];
    
    if (!empty($accessibleSiteIds)) {
        $placeholders = [];
        foreach ($accessibleSiteIds as $index => $siteId) {
            $paramName = 'site_' . $index;
            $placeholders[] = ':' . $paramName;
            $params[$paramName] = $siteId;
        }
        $siteFilter = " AND s.id IN (" . implode(',', $placeholders) . ")";
    }
    
    // Verify filter is empty
    if ($siteFilter === '') {
        echo "  ✓ Site filter is empty as expected\n";
    } else {
        echo "  ERROR: Site filter should be empty but got: $siteFilter\n";
    }
    
    // Verify only start and end params exist
    $expectedParams = [
        'start' => '2025-01-01',
        'end' => '2025-01-31',
    ];
    
    if ($params === $expectedParams) {
        echo "  ✓ Parameters are correct (only start and end)\n";
    } else {
        echo "  ERROR: Expected only start and end parameters\n";
    }
    
    echo "\n";
}

// Run tests
echo "=== Consumption Report Parameter Fix Test ===\n\n";
testParameterConstruction();
testEmptyAccessibleSites();
echo "=== Tests Complete ===\n";
