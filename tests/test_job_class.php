<?php
require_once __DIR__ . '/../models/Job.php';

if (class_exists('Job')) {
    echo "✅ Job class is loaded!";
} else {
    echo "❌ Job class is NOT loaded!";
}
