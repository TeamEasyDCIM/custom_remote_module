<?php

namespace Modules\CustomRemoteModule;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\CustomRemoteModule\Kickstart\CustomKickstart;
use Modules\OsInstallation\Generators\Base\Generator;

/**
 * CustomServiceProvider
 *
 * Debug tip: You can use Laravel's `dd()` (dump and die) anywhere in these listeners 
 * to quickly inspect variables like `$script`, `$params`, `$path`, or `$content` 
 * during the kickstart generation process.
 *
 * Example:
 *     dd($params);
 *
 * ---
 *
 * This service provider hooks into the OS installation kickstart process.
 * It registers event listeners that can:
 *  - Modify the final generated kickstart script (post-processing).
 *  - Modify the raw template before the generator replaces placeholders (pre-processing).
 *
 * This gives you full control to inject custom logic, replace placeholders,
 * or delegate script generation to a custom class.
 */
class CustomServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * Called automatically by Laravel after registration.
     * Here we initialize our event listeners.
     */
    public function boot(): void
    {
        $this->registerKickstartEvents();
    }

    /**
     * Register all kickstart-related event listeners.
     *
     * We listen to:
     *  - "common: os.kickstart.generator": Runs after the script is generated.
     *  - "common: os.kickstart.template.before": Runs before the template content is processed.
     */
    protected function registerKickstartEvents(): void
    {
        // Hook into final script generation (post-processing).
        Event::listen('common: os.kickstart.generator', [$this, 'handleKickstartGenerator']);

        // Hook into template content before placeholder replacement (pre-processing).
        Event::listen('common: os.kickstart.template.before', [$this, 'handleTemplateBefore']);
    }

    /**
     * Handle the "common: os.kickstart.generator" event.
     *
     * This runs *after* the kickstart script has been generated.
     *
     * Responsibilities:
     *  - Calculate CIDR notation from the given netmask
     *    and replace the {{custom:cidrMask}} placeholder in the script.
     *  - If the provisioning template is tagged with "customtag",
     *    pass control to a custom generator (CustomKickstart).
     *
     * @param string $script The generated kickstart script.
     * @param array $params  All parameters provided by the provisioning system.
     * @return string        Modified kickstart script.
     */
    public function handleKickstartGenerator(string $script, array $params): string
    {
        // Replace {{custom:cidrMask}} with a CIDR value calculated from the netmask
        $netmask = array_get($params, 'network.netmask');
        if (!empty($netmask)) {
            $cidrMask = $this->maskToCidr($netmask);
            $script = str_replace('{{custom:cidrMask}}', (string) $cidrMask, $script);
        }

        // If the template has a custom tag, let CustomKickstart take over
        if (array_get($params, 'template.tags') === 'customtag') {
            $kickstart = new CustomKickstart($script, $params);
            $script = $kickstart->generate();
        }

        return $script;
    }

    /**
     * Handle the "common: os.kickstart.template.before" event.
     *
     * This runs *before* the generator processes the raw template content.
     *
     * Responsibilities:
     *  - Replace {{extra}} with a custom string.
     *  - Optionally, you could modify $params or provide an alternate template path.
     *
     * @param Generator $generator The generator instance being used.
     * @param string $path         The file path of the template being read.
     * @param string $content      The raw template file content.
     * @return array|null          An array with modified values:
     *                             - ['content' => '...'] to override content.
     *                             - ['path' => '...'] to point to another template file.
     *                             - ['params' => [...]] to override parameters.
     *                             - null if no changes.
     */
    public function handleTemplateBefore(Generator $generator, string $path, string $content): ?array
    {
        // Simple example: replace a placeholder with a static string
        $content = str_replace('{{extra}}', 'Hello from listener', $content);

        return ['content' => $content];
    }

    /**
     * Convert a netmask to CIDR notation.
     *
     * Example:
     *  - "255.255.255.0" → 24
     *  - "255.255.0.0"   → 16
     *
     * @param string $mask Netmask string (e.g. "255.255.255.0").
     * @return int         CIDR prefix length (e.g. 24).
     */
    private function maskToCidr(string $mask): int
    {
        $long = ip2long($mask);
        $base = ip2long('255.255.255.255');
        return 32 - log(($long ^ $base) + 1, 2);
    }
}