<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>High Priority Alert</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px 20px;
        }
        .alert-box {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-box h2 {
            margin: 0 0 10px 0;
            color: #dc2626;
            font-size: 18px;
        }
        .info-row {
            margin: 10px 0;
            padding: 10px;
            background: #f9fafb;
            border-radius: 4px;
        }
        .info-label {
            font-weight: bold;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            color: #111827;
            font-size: 16px;
            margin-top: 5px;
        }
        .button {
            display: inline-block;
            background: #8b5cf6;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background: #7c3aed;
        }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ High Priority Alert</h1>
            <p style="margin: 10px 0 0 0;">Immediate attention required</p>
        </div>
        
        <div class="content">
            <p>Hello {{ $counselor->name }},</p>
            
            <p>A high-priority emotional pattern alert has been detected for one of your students and requires immediate attention.</p>
            
            <div class="alert-box">
                <h2>{{ $alert->pattern_type === 'consecutive_days' ? 'Consecutive Negative Days' : 'Sudden Emotional Drop' }}</h2>
                <p>{{ $alert->description }}</p>
            </div>
            
            <div class="info-row">
                <div class="info-label">Student</div>
                <div class="info-value">{{ $student->name }}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Alert Type</div>
                <div class="info-value">{{ ucfirst(str_replace('_', ' ', $alert->pattern_type)) }}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Severity</div>
                <div class="info-value" style="color: #ef4444; font-weight: bold;">{{ $alert->severity }}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Detected At</div>
                <div class="info-value">{{ $alert->detected_at->format('F j, Y g:i A') }}</div>
            </div>
            
            <div style="text-align: center;">
                <a href="{{ url('/counselor/alerts') }}" class="button">View Alert Details</a>
            </div>
            
            <p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <strong>Recommended Actions:</strong>
            </p>
            <ul>
                <li>Review the student's emotional trajectory</li>
                <li>Check recent journal entries and chat messages</li>
                <li>Consider scheduling an immediate check-in</li>
                <li>Update the alert status once addressed</li>
            </ul>
        </div>
        
        <div class="footer">
            <p>This is an automated notification from MAGIREM AI</p>
            <p>Please do not reply to this email</p>
        </div>
    </div>
</body>
</html>
