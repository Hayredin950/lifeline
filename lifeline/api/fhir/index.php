<?php
/**
 * FHIR R4 API router — dispatches /api/fhir/{ResourceType}/...
 *
 * Configure Apache/Nginx to route /api/fhir/* → this file, e.g.:
 *   RewriteRule ^api/fhir/(.*)$ api/fhir/index.php [QSA,L]
 */

require_once __DIR__ . '/_bootstrap.php';

$route = fhirRoute();
$type  = $route['resourceType'];

$map = [
    'Patient'        => __DIR__ . '/Patient.php',
    'Observation'    => __DIR__ . '/Observation.php',
    'ServiceRequest' => __DIR__ . '/ServiceRequest.php',
    'metadata'       => __DIR__ . '/metadata.php',
];

if (!isset($map[$type])) {
    fhirError(404, 'error', 'not-found', "Unknown resource type: {$type}");
}

// Each resource file is self-contained; include it to execute.
// _bootstrap.php is already loaded above, so re-require is safe (once).
require $map[$type];
