<?php
return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@PHP8x3Migration' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => true,
        'return_type_declaration' => ['space_before' => 'none'],
    ])
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in([
                'src/PageBuilderComponents/Attribute',
                'src/PageBuilderComponents/Block',
                'src/PageBuilderComponents/Exception',
                'src/PageBuilderComponents/Field',
                'src/PageBuilderComponents/Model',
                'src/PageBuilderComponents/Plugin',
                'src/PageBuilderComponents/Test',
                'src/PageBuilderComponentsExamples/Block',
                'src/PageBuilderComponentsExamples/Test',
                'src/PageBuilderLayout/Model',
                'src/PageBuilderLayout/Test',
                'src/PageBuilderUi/Plugin',
                'src/PageBuilderUi/Test',
                'src/PageBuilderUi/ViewModel',
                'Test/Integration',
            ])
            ->notPath('#/registration\.php$#')
            ->notPath('#(^|/)Screenshots/#')
    );
