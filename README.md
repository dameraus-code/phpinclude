# PHP Include Content Plugin

A Joomla content plugin that replaces include tags with the output of explicitly allowed PHP files. It supports Joomla articles and selected Custom HTML modules.

## Requirements

- Joomla 5 or 6
- PHP files located in a directory under the Joomla root
- A Super User account to create articles that use include tags

## Installation

1. Create a ZIP archive containing `phpinclude.php` and `phpinclude.xml` at the archive root.
2. In Joomla Administrator, go to **System > Install > Extensions** and upload the archive.
3. Go to **System > Manage > Plugins**, open **Content - PHP Include**, and enable it.
4. Configure the plugin settings described below.

## Configuration

| Setting | Description |
| --- | --- |
| Allowed PHP files | A whitelist of PHP file names, one per line. File names may contain letters, numbers, underscores, and hyphens only. Directory paths are not accepted. |
| Include directory | A directory relative to the Joomla root, such as `includes/php`. Leave empty to use the Joomla root. The directory must remain within the Joomla installation. |
| Allowed Custom HTML module IDs | A whitelist of Custom HTML module IDs. Only modules on this list can process include tags. Enter one ID per line; commas, semicolons, and whitespace are also accepted. |
| Keyword | The tag keyword. The default `phpinclude` produces `{phpinclude:filename.php}`. |
| Show error messages | Shows an error in page content when an include is rejected or fails. When disabled, errors are emitted as HTML comments instead. |

## Usage

Place the PHP file in the configured include directory and add its file name to **Allowed PHP files**. Then place the tag in a Joomla article or an allowed Custom HTML module:

```text
{phpinclude:example.php}
```

With the default directory, `example.php` is resolved from the Joomla root. With an include directory of `includes/php`, it is resolved as `includes/php/example.php`.

You can change the keyword in the plugin settings. For example, the keyword `snippet` uses this tag:

```text
{snippet:example.php}
```

Multiple tags can be used in the same content, alongside normal text and HTML.

## Access and Security

- Article tags are processed only when the article author is a Joomla Super User.
- Module tags are processed only in `mod_custom` modules whose IDs are listed in **Allowed Custom HTML module IDs**.
- A requested file must be both whitelisted and present in the configured directory.
- The plugin accepts only simple `.php` file names and rejects paths, traversal sequences, and files outside the configured directory.
- Included files execute with the PHP and Joomla permissions of the web server. Only grant trusted administrators permission to edit the whitelist, include directory, and eligible module content.

## Troubleshooting

- Confirm that the plugin is enabled.
- Verify that the include file name exactly matches an entry in **Allowed PHP files**.
- Verify that the include directory exists under the Joomla root and contains the file.
- For modules, verify that the module is a Custom HTML module and that its numeric ID is listed in **Allowed Custom HTML module IDs**.
- Temporarily enable **Show error messages** to expose blocked, missing, and execution failures in page content.
