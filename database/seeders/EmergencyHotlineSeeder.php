<?php

namespace Database\Seeders;

use App\Models\EmergencyHotline;
use Illuminate\Database\Seeder;

class EmergencyHotlineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotlines = [
            // Global Crisis Hotlines
            [
                'name' => 'International Association for Suicide Prevention',
                'phone' => '+1-888-273-8255',
                'type' => 'suicide',
                'description' => 'International suicide prevention hotline with multilingual support',
                'availability' => '24/7',
                'country' => 'Global',
            ],
            [
                'name' => 'Crisis Text Line',
                'phone' => 'Text HOME to 741741',
                'type' => 'crisis',
                'description' => 'Text-based crisis support available 24/7',
                'availability' => '24/7',
                'country' => 'USA',
            ],
            [
                'name' => 'National Suicide Prevention Lifeline',
                'phone' => '1-800-273-8255',
                'type' => 'suicide',
                'description' => 'Free, confidential support for people in suicidal crisis',
                'availability' => '24/7',
                'country' => 'USA',
            ],
            [
                'name' => 'SAMHSA National Helpline',
                'phone' => '1-800-662-4357',
                'type' => 'mental-health',
                'description' => 'Free, confidential, 24/7 treatment referral and information service',
                'availability' => '24/7',
                'country' => 'USA',
            ],
            [
                'name' => 'National Domestic Violence Hotline',
                'phone' => '1-800-799-7233',
                'type' => 'abuse',
                'description' => 'Support for domestic violence victims and survivors',
                'availability' => '24/7',
                'country' => 'USA',
            ],
            [
                'name' => 'RAINN (Sexual Assault Hotline)',
                'phone' => '1-800-656-4673',
                'type' => 'abuse',
                'description' => 'Confidential support for sexual assault survivors',
                'availability' => '24/7',
                'country' => 'USA',
            ],
            [
                'name' => 'National Eating Disorders Association',
                'phone' => '1-800-931-2237',
                'type' => 'eating-disorder',
                'description' => 'Support and resources for eating disorders',
                'availability' => 'Business hours',
                'country' => 'USA',
            ],
            [
                'name' => 'Substance Abuse and Mental Health Services Administration',
                'phone' => '1-800-662-4357',
                'type' => 'substance-abuse',
                'description' => 'Treatment referral and information service',
                'availability' => '24/7',
                'country' => 'USA',
            ],
            // UK Hotlines
            [
                'name' => 'Samaritans (UK)',
                'phone' => '116 123',
                'type' => 'suicide',
                'description' => 'Emotional support for people experiencing suicidal thoughts',
                'availability' => '24/7',
                'country' => 'UK',
            ],
            [
                'name' => 'Mind (UK)',
                'phone' => '0300 123 3393',
                'type' => 'mental-health',
                'description' => 'Mental health support and information',
                'availability' => 'Business hours',
                'country' => 'UK',
            ],
            // Canada Hotlines
            [
                'name' => 'Canada Suicide Prevention Service',
                'phone' => '1-833-456-4566',
                'type' => 'suicide',
                'description' => 'Free, confidential suicide prevention service',
                'availability' => '24/7',
                'country' => 'Canada',
            ],
            // Australia Hotlines
            [
                'name' => 'Lifeline Australia',
                'phone' => '13 11 14',
                'type' => 'suicide',
                'description' => 'Crisis support and suicide prevention',
                'availability' => '24/7',
                'country' => 'Australia',
            ],
            [
                'name' => 'Beyond Blue',
                'phone' => '1300 224 636',
                'type' => 'mental-health',
                'description' => 'Mental health support and counseling',
                'availability' => '24/7',
                'country' => 'Australia',
            ],
            // University/Student-Specific
            [
                'name' => 'Campus Mind',
                'phone' => '0300 123 3393',
                'type' => 'university',
                'description' => 'Mental health support specifically for university students',
                'availability' => '24/7',
                'country' => 'UK',
            ],
            [
                'name' => 'Student Mental Health Support',
                'phone' => '1-800-STUDENT',
                'type' => 'university',
                'description' => 'Mental health resources and support for students',
                'availability' => 'Business hours',
                'country' => 'USA',
            ],
            // Anxiety & Panic
            [
                'name' => 'Anxiety and Depression Association of America',
                'phone' => '1-240-485-1001',
                'type' => 'anxiety',
                'description' => 'Support and resources for anxiety disorders',
                'availability' => 'Business hours',
                'country' => 'USA',
            ],
            // Grief & Loss
            [
                'name' => 'The Dinner Party',
                'phone' => 'Online support',
                'type' => 'grief',
                'description' => 'Support for young adults grieving loss',
                'availability' => '24/7',
                'country' => 'USA',
            ],
            // LGBTQ+ Support
            [
                'name' => 'The Trevor Project',
                'phone' => '1-866-488-7386',
                'type' => 'lgbtq',
                'description' => 'Crisis support for LGBTQ+ youth',
                'availability' => '24/7',
                'country' => 'USA',
            ],
        ];

        foreach ($hotlines as $hotline) {
            EmergencyHotline::create($hotline);
        }
    }
}
