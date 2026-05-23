<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Web Application',
                'type' => 'webapp',
                'fields' => [
                    ['key' => 'project_name', 'label' => 'Project Name', 'type' => 'text', 'required' => true, 'tooltip' => 'The internal name of your application.'],
                    ['key' => 'problem', 'label' => 'Problem Statement', 'type' => 'textarea', 'required' => true, 'tooltip' => 'What pain point is this application solving? Why is it needed?'],
                    ['key' => 'target_users', 'label' => 'Target Users', 'type' => 'text', 'required' => true, 'tooltip' => 'Who will be using this application? (e.g., Admins, Customers, External Partners)'],
                    ['key' => 'goals', 'label' => 'Goals', 'type' => 'textarea', 'required' => true, 'tooltip' => 'What are the primary objectives or outcomes for this project?'],
                    ['key' => 'constraints', 'label' => 'Constraints', 'type' => 'textarea', 'required' => false, 'tooltip' => 'Technical, financial, or regulatory limitations (e.g., must use React, must be GDPR compliant).'],
                    ['key' => 'stakeholders', 'label' => 'Stakeholders', 'type' => 'text', 'required' => false, 'tooltip' => 'Key decision-makers or sponsors for this project.'],
                    ['key' => 'timeline', 'label' => 'Timeline', 'type' => 'text', 'required' => false, 'tooltip' => 'Desired delivery dates or critical milestones.'],
                ],
            ],
            [
                'name' => 'Mobile Application',
                'type' => 'mobile',
                'fields' => [
                    ['key' => 'project_name', 'label' => 'Project Name', 'type' => 'text', 'required' => true, 'tooltip' => 'The name of your mobile app as it will appear in stores.'],
                    ['key' => 'problem', 'label' => 'Problem Statement', 'type' => 'textarea', 'required' => true, 'tooltip' => 'Why does the user need this on their mobile device?'],
                    ['key' => 'target_users', 'label' => 'Target Users', 'type' => 'text', 'required' => true, 'tooltip' => 'Primary mobile demographic.'],
                    ['key' => 'platform', 'label' => 'Platform (iOS / Android / Both)', 'type' => 'text', 'required' => true, 'tooltip' => 'Which mobile platforms are you targeting?'],
                    ['key' => 'goals', 'label' => 'Goals', 'type' => 'textarea', 'required' => true, 'tooltip' => 'Key metrics for mobile success.'],
                    ['key' => 'constraints', 'label' => 'Constraints', 'type' => 'textarea', 'required' => false, 'tooltip' => 'Hardware requirements or OS limitations.'],
                    ['key' => 'stakeholders', 'label' => 'Stakeholders', 'type' => 'text', 'required' => false, 'tooltip' => 'Mobile app sponsors.'],
                    ['key' => 'timeline', 'label' => 'Timeline', 'type' => 'text', 'required' => false, 'tooltip' => 'Target app store submission dates.'],
                ],
            ],
            [
                'name' => 'API / Microservice',
                'type' => 'api',
                'fields' => [
                    ['key' => 'project_name', 'label' => 'Project Name', 'type' => 'text', 'required' => true, 'tooltip' => 'Name of the service or API.'],
                    ['key' => 'problem', 'label' => 'Problem Statement', 'type' => 'textarea', 'required' => true, 'tooltip' => 'What gap in the architecture does this fill?'],
                    ['key' => 'consumers', 'label' => 'API Consumers', 'type' => 'text', 'required' => true, 'tooltip' => 'Who will call this API? (e.g., Frontend, Mobile App, Other Services)'],
                    ['key' => 'goals', 'label' => 'Goals', 'type' => 'textarea', 'required' => true, 'tooltip' => 'Performance, uptime, or integration targets.'],
                    ['key' => 'constraints', 'label' => 'Constraints', 'type' => 'textarea', 'required' => false, 'tooltip' => 'Rate limits, auth protocols, or latency targets.'],
                    ['key' => 'stakeholders', 'label' => 'Stakeholders', 'type' => 'text', 'required' => false, 'tooltip' => 'Lead developers or architects.'],
                    ['key' => 'timeline', 'label' => 'Timeline', 'type' => 'text', 'required' => false, 'tooltip' => 'Integration and cutover dates.'],
                ],
            ],
            [
                'name' => 'Data Pipeline',
                'type' => 'data',
                'fields' => [
                    ['key' => 'project_name', 'label' => 'Project Name', 'type' => 'text', 'required' => true, 'tooltip' => 'Name of the data project.'],
                    ['key' => 'problem', 'label' => 'Problem Statement', 'type' => 'textarea', 'required' => true, 'tooltip' => 'Why is this data being moved or transformed?'],
                    ['key' => 'data_sources', 'label' => 'Data Sources', 'type' => 'textarea', 'required' => true, 'tooltip' => 'Where is the data coming from? (e.g., MySQL, S3, Salesforce)'],
                    ['key' => 'goals', 'label' => 'Goals', 'type' => 'textarea', 'required' => true, 'tooltip' => 'Accuracy, frequency, or reporting targets.'],
                    ['key' => 'constraints', 'label' => 'Constraints', 'type' => 'textarea', 'required' => false, 'tooltip' => 'Volume, velocity, or data privacy rules.'],
                    ['key' => 'stakeholders', 'label' => 'Stakeholders', 'type' => 'text', 'required' => false, 'tooltip' => 'Data analysts or business users.'],
                    ['key' => 'timeline', 'label' => 'Timeline', 'type' => 'text', 'required' => false, 'tooltip' => 'Target dashboard or report availability.'],
                ],
            ],
            [
                'name' => 'Custom',
                'type' => 'custom',
                'fields' => [
                    ['key' => 'project_name', 'label' => 'Project Name', 'type' => 'text', 'required' => true, 'tooltip' => 'Internal name of the project.'],
                    ['key' => 'problem', 'label' => 'Problem Statement', 'type' => 'textarea', 'required' => true, 'tooltip' => 'Core issue being addressed.'],
                    ['key' => 'target_users', 'label' => 'Target Users', 'type' => 'text', 'required' => true, 'tooltip' => 'Intended audience.'],
                    ['key' => 'goals', 'label' => 'Goals', 'type' => 'textarea', 'required' => true, 'tooltip' => 'Main objectives.'],
                    ['key' => 'constraints', 'label' => 'Constraints', 'type' => 'textarea', 'required' => false, 'tooltip' => 'Limitations or specific requirements.'],
                    ['key' => 'stakeholders', 'label' => 'Stakeholders', 'type' => 'text', 'required' => false, 'tooltip' => 'Project participants.'],
                    ['key' => 'timeline', 'label' => 'Timeline', 'type' => 'text', 'required' => false, 'tooltip' => 'Project schedule.'],
                ],
            ],
        ];

        foreach ($templates as $template) {
            Template::updateOrCreate(['type' => $template['type']], $template);
        }
    }
}
