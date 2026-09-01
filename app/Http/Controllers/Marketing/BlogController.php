<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\System\Models\BlogPost;
use App\Http\Controllers\Controller;
use App\Support\Seo;
use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('marketing.pages.blog.index', [
            'posts' => BlogPost::query()->published()->latest('published_at')->paginate(9),
            'schema' => [
                Seo::organization(),
                Seo::website(),
                Seo::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Blog', 'url' => route('blog.index')],
                ]),
            ],
        ]);
    }

    public function show(BlogPost $post): View
    {
        // An unpublished or scheduled post is a 404 to the public, not a preview.
        abort_if($post->published_at === null || $post->published_at->isFuture(), 404);

        return view('marketing.pages.blog.show', [
            'post' => $post,
            'schema' => [
                Seo::organization(),
                Seo::blogPosting(
                    title: $post->title,
                    description: $post->excerpt,
                    url: route('blog.show', $post->slug),
                    image: asset('images/blog/'.$post->slug.'.png'),
                    author: $post->author,
                    publishedAt: $post->published_at->toIso8601String(),
                ),
                Seo::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Blog', 'url' => route('blog.index')],
                    ['name' => $post->title, 'url' => route('blog.show', $post->slug)],
                ]),
            ],
        ]);
    }
}
