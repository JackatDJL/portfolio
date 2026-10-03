<?php

namespace Tests\Feature;

use App\Support\CvProfileContentResolver;
use App\Support\CvViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Facades\Entry;
use Tests\TestCase;

class CvProfileResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublished_profiles_are_not_resolvable_or_publicly_renderable(): void
    {
        $slug = 'resolver-unpublished-'.bin2hex(random_bytes(5));
        $profile = Entry::make()->collection('cv_profiles')->slug($slug)->published(false)->data([
            'title' => 'Unpublished profile',
            'source_mode' => 'custom',
        ]);
        $profile->save();

        try {
            $this->get('/cv/'.$slug)->assertNotFound();
            $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
            app(CvProfileContentResolver::class)->resolve($slug);
        } finally {
            Entry::delete($profile);
        }
    }

    public function test_published_profiles_do_not_use_unpublished_templates(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $templateSlug = 'resolver-unpublished-template-'.$suffix;
        $profileSlug = 'resolver-template-profile-'.$suffix;
        $template = Entry::make()->collection('cv_profile_templates')->slug($templateSlug)->published(false)->data([
            'title' => 'Draft template '.$suffix,
            'about' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Draft template biography '.$suffix]]]],
        ]);
        $template->save();
        $profile = Entry::make()->collection('cv_profiles')->slug($profileSlug)->published(true)->data([
            'title' => 'Published profile '.$suffix,
            'source_mode' => 'template_only',
            'content_template' => $template->id(),
        ]);
        $profile->save();

        try {
            $resolved = app(CvProfileContentResolver::class)->resolve($profileSlug);
            $pdf = app(CvViewModel::class)->make($profileSlug);

            $this->assertNull($resolved['template']);
            $this->assertSame([], $resolved['sections']['about']['value']);
            $this->assertSame('', $pdf['about']);
            $this->get('/cv/'.$profileSlug)->assertOk()->assertDontSee('Draft template biography '.$suffix);
        } finally {
            Entry::delete($profile);
            Entry::delete($template);
        }
    }

    public function test_custom_profiles_inherit_defaults_and_public_identity_comes_from_site(): void
    {
        $resolver = app(CvProfileContentResolver::class);
        $default = $resolver->resolve();
        $custom = $resolver->resolve('jobmesse-26');
        $pdf = app(CvViewModel::class)->make('jobmesse-26');
        $response = $this->get('/cv/jobmesse-26')->assertOk();
        $legacySlug = 'resolver-legacy-'.bin2hex(random_bytes(5));
        $legacy = Entry::make()->collection('cv_profiles')->slug($legacySlug)->published(true)->data([
            'title' => 'Legacy profile',
            'source_mode' => 'custom',
            'about' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Legacy profile biography']]]],
        ]);
        $legacy->save();

        try {
            $legacyResolved = $resolver->resolve($legacySlug);
            $this->assertSame('custom', $custom['source_mode']);
            $this->assertSame($default['sections']['about']['value'], $custom['sections']['about']['value']);
            $this->assertSame('Legacy profile biography', $this->plainText($legacyResolved['sections']['about']['value']));
            $this->assertSame($default['public_identity']['name'], $custom['public_identity']['name']);
            $this->assertSame('https://jack.djl.foundation', $custom['public_identity']['website']);
            $this->assertSame($default['sections']['about']['value'], $resolver->resolve()['sections']['about']['value']);
            $this->assertSame($this->plainText($custom['sections']['about']['value']), $pdf['about']);
            $this->assertStringContainsString('https://jack.djl.foundation', $response->getContent());
            $this->assertSame('https://jack.djl.foundation', $pdf['contact']['website']);
        } finally {
            Entry::delete($legacy);
        }

        $page = $this->get('/cv')->assertOk();
        preg_match('/<nav class="site-footer__nav".*?<\/nav>/s', $page->getContent(), $footer);
        preg_match('/<nav class="menu-panel__footer".*?<\/nav>/s', $page->getContent(), $menu);
        $this->assertStringContainsString('https://github.com/JackatDJL', $footer[0] ?? '');
        $this->assertStringContainsString('https://codeberg.org/jkxrx', $footer[0] ?? '');
        $this->assertStringContainsString('https://github.com/JackatDJL', $menu[0] ?? '');
        $this->assertStringContainsString('https://codeberg.org/jkxrx', $menu[0] ?? '');
    }

    public function test_template_inheritance_override_hide_and_template_only_match_in_web_and_pdf(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $templateSlug = 'resolver-'.$suffix;
        $overrideSlug = 'resolver-override-'.$suffix;
        $onlySlug = 'resolver-only-'.$suffix;
        $template = Entry::make()->collection('cv_profile_templates')->slug($templateSlug)->published(true)->data([
            'title' => 'Resolver template '.$suffix,
            'about' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Template biography marker '.$suffix]]]],
            'knowledge' => [['category' => 'Template knowledge '.$suffix, 'items' => [['label' => 'Inherited item '.$suffix]]]],
            'soft_skills' => [['label' => 'Template soft skill '.$suffix]],
            'projects' => [],
            'publications' => [],
            'milestones' => [],
            'interactive_timeline' => false,
        ]);
        $template->save();
        $override = Entry::make()->collection('cv_profiles')->slug($overrideSlug)->published(true)->data([
            'title' => 'Template with overrides '.$suffix,
            'source_mode' => 'template_override',
            'content_template' => $template->id(),
            'about_source' => 'inherit',
            'about' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Must not replace inherited template content '.$suffix]]]],
            'knowledge_source' => 'override',
            'knowledge' => [],
            'soft_skills_source' => 'hide',
            'projects_source' => 'inherit',
            'publications_source' => 'override',
            'publications' => [],
            'milestones_source' => 'inherit',
            'interactive_timeline' => 'inherit',
        ]);
        $override->save();
        $only = Entry::make()->collection('cv_profiles')->slug($onlySlug)->published(true)->data([
            'title' => 'Template only '.$suffix,
            'source_mode' => 'template_only',
            'content_template' => $template->id(),
            'about' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Must be ignored '.$suffix]]]],
        ]);
        $only->save();

        try {
            $resolved = app(CvProfileContentResolver::class)->resolve($overrideSlug);
            $pdf = app(CvViewModel::class)->make($overrideSlug);
            $web = $this->get('/cv/'.$overrideSlug)->assertOk();
            $this->assertSame('Template biography marker '.$suffix, $this->plainText($resolved['sections']['about']['value']));
            $this->assertSame($this->plainText($resolved['sections']['about']['value']), $pdf['about']);
            $this->assertSame([], $resolved['sections']['knowledge']['value']);
            $this->assertSame('hide', $resolved['sections']['soft_skills']['source']);
            $this->assertSame([], $resolved['sections']['publications']['value']);
            $this->assertFalse($resolved['interactive_timeline']);
            $this->assertSame([], $pdf['knowledge']);
            $this->assertSame([], $pdf['soft_skills']);
            $this->assertSame([], $pdf['publications']);
            $web->assertSee('Template biography marker '.$suffix)
                ->assertDontSee('Template soft skill '.$suffix)
                ->assertDontSee('<h2 id="cv-soft-skills-title">Soft Skills</h2>')
                ->assertDontSee('<h2 id="cv-publications-title">Veröffentlichungen</h2>');

            $templateOnly = app(CvProfileContentResolver::class)->resolve($onlySlug);
            $templateOnlyPdf = app(CvViewModel::class)->make($onlySlug);
            $this->assertSame('template_only', $templateOnly['source_mode']);
            $this->assertSame('Template biography marker '.$suffix, $this->plainText($templateOnly['sections']['about']['value']));
            $this->assertSame('Template knowledge '.$suffix, $templateOnly['sections']['knowledge']['value'][0]['category']);
            $this->assertSame($templateOnly['sections']['knowledge']['value'], $templateOnlyPdf['knowledge']);
            $this->assertFalse($templateOnly['interactive_timeline']);
            $this->get('/cv/'.$onlySlug)->assertOk()
                ->assertSee('Template biography marker '.$suffix)
                ->assertSee('Inherited item '.$suffix)
                ->assertDontSee('Must be ignored '.$suffix);
        } finally {
            Entry::delete($only);
            Entry::delete($override);
            Entry::delete($template);
        }
    }

    private function plainText(mixed $value): string
    {
        if (is_string($value)) {
            return trim(strip_tags($value));
        }
        $parts = [];
        $walk = function (array $nodes) use (&$walk, &$parts): void {
            foreach ($nodes as $node) {
                if (isset($node['text'])) {
                    $parts[] = $node['text'];
                }
                if (isset($node['content']) && is_array($node['content'])) {
                    $walk($node['content']);
                }
            }
        };
        if (is_array($value)) {
            $walk($value);
        }

        return trim(implode(' ', $parts));
    }
}
