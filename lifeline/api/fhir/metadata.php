<?php
/**
 * FHIR R4 CapabilityStatement — GET /api/fhir/metadata
 *
 * Describes what this server supports, enabling EHR auto-discovery.
 * No auth required (per FHIR §2.8).
 */

if (!defined('FHIR_BASE_URL')) {
    require_once __DIR__ . '/_bootstrap.php';
}

fhirOk([
    'resourceType'   => 'CapabilityStatement',
    'id'             => 'lifeline-fhir-capability',
    'status'         => 'active',
    'kind'           => 'instance',
    'date'           => date('Y-m-d'),
    'software'       => [
        'name'    => 'LifeLine Blood Network',
        'version' => '1.0',
    ],
    'implementation' => [
        'description' => 'LifeLine Blood Network FHIR R4 API — Ethiopian National Blood System',
        'url'         => FHIR_BASE_URL,
    ],
    'fhirVersion'    => '4.0.1',
    'format'         => ['application/fhir+json'],
    'rest'           => [[
        'mode'      => 'server',
        'security'  => [
            'cors'        => true,
            'description' => 'Bearer token (same key as /api/v1). Scope: fhir or *.',
        ],
        'resource'  => [
            [
                'type'        => 'Patient',
                'interaction' => [
                    ['code' => 'read'],
                    ['code' => 'search-type'],
                ],
                'searchParam' => [
                    ['name' => '_id',       'type' => 'token'],
                    ['name' => 'blood-type','type' => 'token'],
                    ['name' => 'city',      'type' => 'string'],
                    ['name' => 'state',     'type' => 'string'],
                ],
            ],
            [
                'type'        => 'Observation',
                'interaction' => [
                    ['code' => 'read'],
                    ['code' => 'search-type'],
                ],
                'searchParam' => [
                    ['name' => 'subject', 'type' => 'reference'],
                    ['name' => '_id',     'type' => 'token'],
                ],
            ],
            [
                'type'        => 'ServiceRequest',
                'interaction' => [
                    ['code' => 'read'],
                    ['code' => 'search-type'],
                    ['code' => 'create'],
                ],
                'searchParam' => [
                    ['name' => 'status',     'type' => 'token'],
                    ['name' => 'blood-type', 'type' => 'token'],
                    ['name' => 'urgency',    'type' => 'token'],
                    ['name' => 'city',       'type' => 'string'],
                ],
            ],
        ],
    ]],
]);
