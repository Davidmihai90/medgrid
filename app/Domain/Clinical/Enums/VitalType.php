<?php

namespace App\Domain\Clinical\Enums;

enum VitalType: string
{
    case HeartRate = 'HEART_RATE';
    case BloodPressure = 'BLOOD_PRESSURE';
    case Spo2 = 'SPO2';
    case RespiratoryRate = 'RESPIRATORY_RATE';
    case Temperature = 'TEMPERATURE';
    case Glucose = 'GLUCOSE';
    case Gcs = 'GCS';
    case PainScore = 'PAIN_SCORE';
}
