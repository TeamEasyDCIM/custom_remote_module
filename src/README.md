# Remote Agent – Event Driven Extensions

This repository/module is designed to support a **remote agent** that can be extended with **event-driven hooks**.  
Using Laravel’s `Event::listen` you can easily plug in your own logic without modifying the core code.

---

## How it works

1. **Core modules** (e.g. OS Installation) generate scripts, perform actions or fetch templates.  
2. **Events** are fired at different stages of the process:
   - `*.template.before` → before a template file or partial is used.  
   - `*.generator` → after a script is generated.  
   - In the future: more events for provisioning lifecycle, remote commands, etc.
3. **Custom service providers** (e.g. `CustomServiceProvider`) register listeners for these events.  
4. **Listeners** can modify parameters, replace template content, inject new placeholders, or even fully override the generated output.

---

## Example Service Provider

You can create your own provider (e.g. `CustomServiceProvider`) and register event listeners:

```php
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\OsInstallation\Generators\Base\Generator;
use Modules\CustomRemoteModule\Kickstart\CustomKickstart;

class CustomServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen('common: os.kickstart.template.before', [$this, 'onTemplateBefore']);
        Event::listen('common: os.kickstart.generator', [$this, 'onGenerator']);
    }

    public function onTemplateBefore(Generator $g, string $path, string $content): ?array
    {
        // Replace placeholder {{extra}} in any template
        $content = str_replace('{{extra}}', 'Hello from listener', $content);

        return ['content' => $content];
    }

    public function onGenerator(string $script, array $params): string
    {
        // Example: add a calculated CIDR mask
        $netmask = array_get($params, 'network.netmask');
        if ($netmask) {
            $cidr = 32 - log((ip2long($netmask) ^ ip2long('255.255.255.255')) + 1, 2);
            $script = str_replace('{{custom:cidrMask}}', $cidr, $script);
        }

        // Example: delegate to your own generator if a tag is present
        if (array_get($params, 'template.tags') === 'customtag') {
            $kickstart = new CustomKickstart($script, $params);
            $script = $kickstart->generate();
        }

        return $script;
    }
}
````

⚡ **Debug tip**: You can use Laravel’s `dd()` inside listeners to inspect variables during runtime.

---

## Example: AlmaLinux 10 Kickstart

Here’s how the `Almalinux10` generator works:

* Loads the main template (`almalinux10.ks`) with `$this->fetchTemplate()`.
* Includes partials with `$this->getTemplate('almalinux10/...')`.
* Replaces placeholders like `{{ip}}`, `{{netmask}}`, `{{packages}}`, `{{post_installation}}`, etc.

Here’s a simplified `.ks` file for AlmaLinux 10:

````ks
skipx
text
reboot

lang {{lang}}
keyboard us
timezone {{timezone}}

authselect --useshadow --passalgo=md5
rootpw --iscrypted "{{rootpasswordmd5}}"
{{userconfig}}
selinux --disabled
url --noverifyssl --url http://{{pxeserver}}/os/downloader/{{templateid}}/{{mac}}/BaseOS/{{edition}}/kickstart
repo --name=easydcimstream --baseurl="http://{{pxeserver}}/os/downloader/{{templateid}}/{{mac}}/BaseOS/{{edition}}/kickstart"

bootloader --location=mbr
zerombr
clearpart --all --initlabel
{{disklayout}}

%include /tmp/eth-configuration
firewall --disabled

%packages --ignoremissing
{{packages}}
%end

%pre

{{networkconfig}}

%end

%post --log=/root/ks-post.log

curl -O "http://{{pxeserver}}/os/kickstart/provision-status?mac={{mac}}&message=Post-installation+scripts"

{{post_installation}}

{{first_boot}}

curl --silent -o /dev/null "http://{{pxeserver}}/os/kickstart/complete?mac={{mac}}"

%end
````

The cleanest way to replace placeholders like `{{userconfig}}` or `{{packages}}` is to modify the **generator parameters** and/or the **partial template** in the  
`common: os.kickstart.template.before` event.  

This way, the generator works as usual but uses your custom data when performing the final `str_replace`.

### Listener Example

````php
use Illuminate\Support\Facades\Event;
use Modules\OsInstallation\Generators\Base\Generator;

Event::listen('common: os.kickstart.template.before', function (Generator $g, string $path, string $content) {
    $p = $g->getParams();

    // Existing packages (split into array by newlines)
    $current = preg_split('/\r\n|\r|\n/', $p['provision']['packages'] ?? $p['packages'] ?? '');
    $current = array_filter(array_map('trim', $current)); // clean blanks

    // Extra packages to add
    $extra = ['vim', 'htop', 'curl'];

    // Merge and deduplicate
    $allPackages = array_unique(array_merge($current, $extra));

    // Store back as newline-separated string
    $p['provision']['packages'] = implode("\n", $allPackages);

    return ['params' => $p];
});
````

### How it works

* **Packages (`{{packages}}`)**
  We directly set `provision.packages` in the generator’s params.
  The generator later uses this value when replacing `{{packages}}`.

* **User config (`{{userconfig}}`)**
  The AlmaLinux 10 generator loads a partial template called
  `almalinux10/user-configuration.ks`.
  By checking the `$path` value in the listener, we can override its content.
  Inside that content, we can still use placeholders like `{{username}}` or
  `{{userpasswordmd5}}`, which will be filled automatically in the final step.

### Result in the generated kickstart

````ks
%packages --ignoremissing
vim
htop
curl
%end

user --name=user --password=$1$abcd1234$efgh5678ijklmnopqrstu --iscrypted --uid=1000
````