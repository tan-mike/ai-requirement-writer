<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Low-Score Threshold
    |--------------------------------------------------------------------------
    |
    | A criterion scored at or below this value requires at least one issue
    | tag from that criterion's tag set before the draft can be approved.
    */
    'low_score_threshold' => 2,

    /*
    |--------------------------------------------------------------------------
    | Scoring Criteria
    |--------------------------------------------------------------------------
    |
    | Each criterion is scored independently on a 1-5 scale. The tag set is
    | scoped per criterion — a tag is only valid for its own criterion.
    */
    'criteria' => [
        'domain_accuracy' => [
            'label' => 'Domain Accuracy',
            'question' => 'How factually accurate is this document in relation to the provided source context, and is it completely free of hallucinated features or assumptions?',
            'anchors' => [
                1 => 'Highly inaccurate / major hallucinations',
                5 => 'Perfectly grounded to the provided context',
            ],
            'tags' => [
                'hallucinated_feature' => 'Hallucinated feature',
                'contradicts_context' => 'Contradicts source context',
                'wrong_domain_term' => 'Wrong domain terminology',
                'unsupported_assumption' => 'Unsupported assumption',
                'other' => 'Other',
            ],
        ],
        'structural_completeness' => [
            'label' => 'Structural Completeness',
            'question' => 'How well does this output adhere to the required formatting rules, including all mandatory sections (e.g., Executive Summary, User Stories)?',
            'anchors' => [
                1 => 'Missing critical sections or formatting',
                5 => 'Structurally perfect and fully compliant',
            ],
            'tags' => [
                'missing_section' => 'Missing mandatory section',
                'wrong_format' => 'Wrong format',
                'incomplete_items' => 'Incomplete items',
                'poor_organization' => 'Poor organization',
                'other' => 'Other',
            ],
        ],
    ],
];
