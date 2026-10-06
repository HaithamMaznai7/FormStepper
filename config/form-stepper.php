<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\Http\Controllers\Admin\FormController as AdminFormController;
use HaithamMaznai\FormStepper\Http\Controllers\Admin\InputController;
use HaithamMaznai\FormStepper\Http\Controllers\Admin\InputTypeController;
use HaithamMaznai\FormStepper\Http\Controllers\Admin\StepTemplateController;
use HaithamMaznai\FormStepper\Http\Controllers\FormController;
use HaithamMaznai\FormStepper\Http\Resources\FormResource;
use HaithamMaznai\FormStepper\Http\Resources\RepeatableResource;
use HaithamMaznai\FormStepper\Http\Resources\RequirementResource;
use HaithamMaznai\FormStepper\Http\Resources\StepResource;

return [
    /*
    |--------------------------------------------------------------------------
    | Package status
    |--------------------------------------------------------------------------
    */
    'enabled' => true, // Enable or disable the package globally.

    /*
    |--------------------------------------------------------------------------
    | Default form settings
    |--------------------------------------------------------------------------
    */
    'placeholder' => 'default', // Default placeholder value used when no explicit value is provided.
    'default_mode' => 'stepper', // Default UI mode: 'single' or 'stepper'.
    'allow_guest' => true, // Whether unauthenticated users can access forms.
    'allow_instance_mode_override' => false, // Allows a form instance to override the default mode.

    /*
    |--------------------------------------------------------------------------
    | Tenant configuration
    |--------------------------------------------------------------------------
    */
    'tenant' => [
        'enabled' => false, // Enable tenant-aware form handling.
        'relationship' => 'currentTenant', // Model relationship used to resolve the current tenant.
    ],

    'throw_on_extra_values' => false, // Throw an exception when request payload contains keys not defined in the form schema.

    /*
    |--------------------------------------------------------------------------
    | Database table names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'forms' => 'forms', // Main forms table.
        'options' => 'form_options', // Form option values table.
        'steps' => 'form_steps', // Form steps table.
        'input_types' => 'form_input_types', // Managed input types (system + custom).
        'inputs' => 'form_inputs', // Reusable lookup inputs.
        'input_children' => 'form_input_children', // Ordered children of complex lookup inputs.
        'step_templates' => 'form_steps_library', // Reusable library steps.
        'step_template_inputs' => 'form_steps_library_inputs', // Ordered inputs of library steps.
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin pages
    |--------------------------------------------------------------------------
    | Blade pages to manage input types, lookup inputs, library steps, and forms.
    | Disabled by default. When `gate` is set, users must pass that Gate; define
    | it in your AuthServiceProvider/AppServiceProvider, otherwise access is denied.
    */
    'admin' => [
        'enabled' => false, // Register the admin routes.
        'prefix' => 'form-stepper/admin', // Base URL for admin pages.
        'middleware' => ['web', 'auth'], // Middleware applied to admin routes.
        'name' => 'form-stepper.admin.', // Route naming prefix.
        'gate' => 'manage-form-stepper', // Gate ability required; null disables the check.
        'per_page' => 15, // Rows per admin listing page.
        'controllers' => [
            'input_types' => InputTypeController::class,
            'inputs' => InputController::class,
            'steps' => StepTemplateController::class,
            'forms' => AdminFormController::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => true, // Enable package routes.
        'prefix' => 'api/forms', // Base URL prefix for form routes.
        'middleware' => ['api'], // Middleware applied to routes.
        'name' => 'form-stepper.forms.', // Route naming prefix.
        'controller' => FormController::class, // Controller used to handle form endpoints.
    ],

    /*
    |--------------------------------------------------------------------------
    | API resources
    |--------------------------------------------------------------------------
    | Replace any level of the form response with your own class. Custom classes
    | should extend the package resource they replace.
    */
    'resources' => [
        'form' => FormResource::class, // Whole form response.
        'step' => StepResource::class, // Each step (or single-mode container).
        'requirement' => RequirementResource::class, // Each input, including complex children.
        'repeatable' => RepeatableResource::class, // Each saved instance of a repeatable step.
    ],

    /*
    |--------------------------------------------------------------------------
    | Builder and type configuration
    |--------------------------------------------------------------------------
    */
    'builders' => [], // Custom builders registered for forms.
    'types' => [
        'b2c',
        'b2b',
        'b2partner',
    ], // Supported form types used by the application.
    'default_type' => 'b2c', // Default form type applied when none is specified.

    /*
    |--------------------------------------------------------------------------
    | Step navigation settings
    |--------------------------------------------------------------------------
    */
    'default_step' => null, // Default step slug used when no step is selected.
    'last_step' => 'checkout', // Final step slug for the checkout flow.

    /*
    |--------------------------------------------------------------------------
    | Saleable configuration
    |--------------------------------------------------------------------------
    */
    'saleables' => [
        // Add saleable model mappings here.
    ],
];
