<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Certification;
use App\Models\ContactMessage;
use App\Models\Diploma;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Hobby;
use App\Models\Page;
use App\Models\Project;
use App\Models\Skill;
use App\Models\Theme;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                ['label' => 'Projets',        'count' => Project::count(),       'route' => 'admin.projets.index'],
                ['label' => 'Articles',       'count' => Article::count(),       'route' => 'admin.articles.index'],
                ['label' => 'Thèmes',         'count' => Theme::count(),         'route' => 'admin.themes.index'],
                ['label' => 'Compétences',    'count' => Skill::count(),         'route' => 'admin.competences.index'],
                ['label' => 'Formations',     'count' => Education::count(),     'route' => 'admin.formations.index'],
                ['label' => 'Expériences',    'count' => Experience::count(),    'route' => 'admin.experiences.index'],
                ['label' => 'Loisirs',        'count' => Hobby::count(),         'route' => 'admin.loisirs.index'],
                ['label' => 'Diplômes',       'count' => Diploma::count(),       'route' => 'admin.diplomes.index'],
                ['label' => 'Certifications', 'count' => Certification::count(), 'route' => 'admin.certifications.index'],
            ],
            'visits'         => app(\App\Services\SiteStatistics::class)->summary(7),
            'unreadCount'    => ContactMessage::whereNull('read_at')->count(),
            'latestMessages' => ContactMessage::latest()->limit(5)->get(),
            'privatePages'   => Page::allOrdered()->where('is_public', false),
            'pendingArticles' => Article::pendingReview()->with('author')->oldest('submitted_at')->get(),
            'drafts'         => Article::where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '>', now()))
                ->latest('updated_at')->limit(5)->get(),
        ]);
    }
}
