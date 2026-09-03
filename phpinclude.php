<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;

class PlgContentPhpinclude extends CMSPlugin
{
    protected $autoloadLanguage = false;

    /**
     * Process Joomla article content.
     *
    * Supported syntax:
    *   phpinclude:myfile.php
    *   <p>phpinclude:myfile.php</p>
     *
     * Multiple includes and normal HTML/text can be mixed.
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        $application = $this->getApplication();

        if ($application !== null && $application->isClient('administrator')) {
            return;
        }

        if (!isset($article->text) || !is_string($article->text)) {
            return;
        }

        if (!isset($article->created_by) || !$this->isSuperAdmin((int) $article->created_by)) {
            return;
        }

        $article->text = $this->processIncludes($article->text);
    }

    /**
    * Process PHP include tags in whitelisted Custom HTML modules.
     */
    public function onAfterRenderModule($module, $attributes = array())
    {
        $application = $this->getApplication();

        if (
            ($application !== null && $application->isClient('administrator')) ||
            !is_object($module) ||
            !isset($module->module, $module->id, $module->content) ||
            $module->module !== 'mod_custom' ||
            !is_string($module->content) ||
            !$this->isAllowedModule((int) $module->id)
        ) {
            return;
        }

        $module->content = $this->processIncludes($module->content);
    }

    /**
     * Replace include tags with the output from allowed PHP files.
     */
    private function processIncludes(string $content): string
    {
        $allowed = $this->getAllowedFiles();

        if (!$allowed) {
            return $content;
        }

        $directory = $this->getIncludeDirectory();

        if ($directory === false) {
            return $content;
        }

        $keyword = trim((string) $this->params->get('keyword', 'phpinclude'), ": \t\n\r\0\x0B");

        if ($keyword === '') {
            $keyword = 'phpinclude';
        }

        $showErrors = (bool) $this->params->get('show_errors', 0);

        /*
         * Matchar t.ex.:
         *
         * {phpinclude:test.php}
         *
         * Filnamnet får endast innehålla:
         * A-Z, a-z, 0-9, _ och -
         */
        $pattern = '~\{' . preg_quote($keyword, '~') . ':([A-Za-z0-9_-]+\.php)\}~i';

        return preg_replace_callback(
            $pattern,
            function ($matches) use ($allowed, $directory, $showErrors) {

                $filename = $matches[1];

                // Filen måste finnas i whitelist.
                if (!in_array($filename, $allowed, true)) {
                    return $this->renderMessage(
                        $showErrors,
                        'PHP include blocked',
                        'The file "' . $filename . '" is not in the allowlist.'
                    );
                }

                $file = $directory . DIRECTORY_SEPARATOR . $filename;
                $realFile = realpath($file);

                if (
                    $realFile === false ||
                    !is_file($realFile) ||
                    strtolower(pathinfo($realFile, PATHINFO_EXTENSION)) !== 'php' ||
                    dirname($realFile) !== $directory
                ) {
                    return $this->renderMessage(
                        $showErrors,
                        'PHP include rejected',
                        'The file "' . $filename . '" is missing.'
                    );
                }

                /*
                 * Kör PHP-filen och fånga dess output.
                 */
                ob_start();

                try {
                    include $realFile;

                    return ob_get_clean();

                } catch (\Throwable $e) {

                    ob_end_clean();

                    // Visa inte PHP-felet för besökaren.
                    return $this->renderMessage(
                        $showErrors,
                        'PHP include error',
                        'The file "' . $filename . '" could not be executed.'
                    );
                }
            },
            $content
        );
    }

    /**
    * Build an error message, either visible in the content or hidden in an HTML comment.
     */
    private function renderMessage(bool $visible, string $label, string $message): string
    {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        if ($visible) {
            return '<p class="php-include-error">' . $safeMessage . '</p>';
        }

        return '<!-- ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ': ' . $safeMessage . ' -->';
    }

    /**
    * Check whether the specified user is a super user.
     */
    private function isSuperAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = Factory::getContainer()
            ->get(UserFactoryInterface::class)
            ->loadUserById($userId);

        return $user->authorise('core.admin');
    }

    /**
     * Check whether the module is explicitly allowed in the plugin settings.
     */
    private function isAllowedModule(int $moduleId): bool
    {
        if ($moduleId <= 0) {
            return false;
        }

        return in_array($moduleId, $this->getAllowedModuleIds(), true);
    }

    /**
     * Get and validate the whitelist from the plugin settings.
     */
    private function getAllowedFiles()
    {
        $value = (string) $this->params->get('allowed_files', '');

        $files = preg_split('/[\r\n,;]+/', $value);

        $result = array();

        foreach ($files as $file) {

            $file = trim($file);

            if ($file === '') {
                continue;
            }

            if (!preg_match('/^[A-Za-z0-9_-]+\.php$/i', $file)) {
                continue;
            }

            $result[] = $file;
        }

        return array_values(array_unique($result));
    }

    /**
     * Get the whitelist of Custom HTML module IDs from the plugin settings.
     */
    private function getAllowedModuleIds(): array
    {
        $value = (string) $this->params->get('allowed_modules', '');
        $moduleIds = preg_split('/[\s,;]+/', trim($value));
        $result = array();

        foreach ($moduleIds as $moduleId) {
            if (ctype_digit($moduleId) && (int) $moduleId > 0) {
                $result[] = (int) $moduleId;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Get the configured include directory, constrained to Joomla's root directory.
     *
     * @return string|false
     */
    private function getIncludeDirectory()
    {
        $root = realpath(JPATH_ROOT);
        $value = trim((string) $this->params->get('include_directory', ''), " /\\\t\n\r\0\x0B");

        if ($root === false) {
            return false;
        }

        if ($value === '') {
            return $root;
        }

        if (!preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $value)) {
            return false;
        }

        $directory = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $value));

        if ($directory === false || !is_dir($directory) || strpos($directory . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) !== 0) {
            return false;
        }

        return $directory;
    }
}
