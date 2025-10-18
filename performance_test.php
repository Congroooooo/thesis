<?php
/**
 * Simple performance monitoring script for ProItemList.php
 */

function testPageLoad($url, $iterations = 3) {
    $times = [];
    
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'method' => 'GET',
                'header' => "User-Agent: Performance Test Script\r\n"
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        $end = microtime(true);
        
        if ($content !== false) {
            $times[] = $end - $start;
            echo "Test " . ($i + 1) . ": " . round(($end - $start) * 1000, 2) . " ms\n";
        } else {
            echo "Test " . ($i + 1) . ": Failed to load\n";
        }
        
        // Small delay between tests
        usleep(100000); // 100ms
    }
    
    if (!empty($times)) {
        $average = array_sum($times) / count($times);
        echo "\nAverage load time: " . round($average * 1000, 2) . " ms\n";
        echo "Best time: " . round(min($times) * 1000, 2) . " ms\n";
        echo "Worst time: " . round(max($times) * 1000, 2) . " ms\n";
    }
}

// Test the optimized page
echo "Testing ProItemList.php performance...\n";
echo "==========================================\n";

$baseUrl = "http://localhost:3000/Pages/ProItemList.php";

echo "Testing with cache:\n";
testPageLoad($baseUrl);

echo "\nTesting without cache:\n";
testPageLoad($baseUrl . "?clear_cache=1");

echo "\nPerformance test completed!\n";
echo "==========================================\n";
echo "If you see consistent load times under 2000ms, the optimizations are working well.\n";
echo "If you still see ERR_INCOMPLETE_CHUNKED_ENCODING, check your web server configuration.\n";
?>