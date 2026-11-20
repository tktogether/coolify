<?php

/*
 * Fork-owned helpers. Keeping them in their own file means the hourly upstream
 * sync never has to resolve a conflict in bootstrap/helpers/shared.php for them.
 */

/**
 * The registry the Coolify image and its upgrade script are pulled from.
 *
 * The fork publishes its images under a registry path (for example
 * ghcr.io/tktogether/coollabsio/coolify), which the docker.io/ghcr.io registry
 * setting cannot express. A REGISTRY_URL configured in the environment therefore
 * wins over that setting; the setting decides when the environment leaves it at
 * the docker.io default.
 */
function coolifyUpgradeRegistryUrl(): string
{
    $configured = config('constants.coolify.registry_url', 'docker.io');

    if (is_string($configured) && $configured !== '' && $configured !== 'docker.io') {
        return $configured;
    }

    return coolifyRegistryUrl();
}
