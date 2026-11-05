<?php

namespace Javaabu\Meta\Tests\Unit;

use Javaabu\Meta\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DocsTest extends TestCase
{
    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<int, string>
     */
    protected function docsPages(): array
    {
        $pages = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root() . '/docs'));

        foreach ($files as $file) {
            if ($file->getExtension() === 'md') {
                $pages[] = 'docs/' . ltrim(str_replace($this->root() . '/docs', '', $file->getPathname()), '/');
            }
        }

        sort($pages);

        return $pages;
    }

    #[Test]
    public function llms_txt_links_every_docs_page(): void
    {
        $llms = file_get_contents($this->root() . '/llms.txt');

        foreach ($this->docsPages() as $page) {
            $this->assertStringContainsString("({$page})", $llms, "llms.txt doesn't link {$page}");
        }
    }

    #[Test]
    public function relative_links_in_the_docs_resolve(): void
    {
        foreach ([...$this->docsPages(), 'llms.txt', 'README.md', 'resources/boost/skills/javaabu-meta/SKILL.md'] as $page) {
            $contents = file_get_contents($this->root() . '/' . $page);

            preg_match_all('/\]\((?!https?:|#|mailto:)([^)#\s]+)(?:#[^)]*)?\)/', $contents, $matches);

            foreach ($matches[1] as $link) {
                // GitHub's contributors page, relative to the repository URL.
                if ($link === '../../contributors') {
                    continue;
                }

                $target = realpath(dirname($this->root() . '/' . $page) . '/' . $link);

                $this->assertNotFalse($target, "{$page} links to {$link}, which doesn't exist");
            }
        }
    }
}
