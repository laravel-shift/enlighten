<?php

namespace Styde\Enlighten\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Styde\Enlighten\Facades\Enlighten;
use Styde\Enlighten\Models\Area;
use Styde\Enlighten\Models\Module;
use Styde\Enlighten\Models\ModuleCollection;
use Styde\Enlighten\Models\Run;
use Styde\Enlighten\Section;

class ShowAreaController
{
    public function __invoke(Run $run, string $areaSlug = null)
    {
        $viewMethod = 'view' . ucfirst(config('enlighten.area_view', 'modules'));

        if (!method_exists($this, $viewMethod)) {
            $viewMethod = 'viewModules';
        }

        return $this->$viewMethod($run, $this->getArea($run, $areaSlug));
    }

    private function viewModules(Run $run, Area $area = null)
    {
        return view('enlighten::area.modules', [
            'area' => $area,
            'modules' => $this->getModules($this->getGroups($run, $area)->load('stats')),
        ]);
    }

    private function viewFeatures(Run $run, Area $area = null)
    {
        $groups = $this->getGroups($run, $area)
            ->load([
                'examples' => function ($q) {
                    $q->withCount('queries');
                },
                'examples.group',
                'examples.requests',
                'examples.exception'
            ]);

        return view('enlighten::area.features', [
            'area' => $area,
            'showQueries' => Enlighten::show(Section::QUERIES),
            'groups' => $groups,
        ]);
    }

    private function viewEndpoints(Run $run, Area $area = null)
    {
        $examples = $run->examples()
            ->with([
                'group',
                'requests' => function ($q) {
                    $q->select(
                        'id',
                        'example_id',
                        'request_method',
                        'request_path',
                        'route',
                        'response_status',
                        'response_headers',
                    );
                }
            ])
            ->get();

        $endpoints = $examples
            ->pluck('requests')
            ->flatten()
            ->sortBy('id')
            ->each(function ($request) use ($examples) {
                $request->setRelation('example', $examples->firstWhere('id', $request->example_id));
            })
            ->groupBy(function ($request) {
                return $request->request_method.' '.($request->route ?: $request->request_path);
            })
            ->sortBy(function ($requests, $endpoint) {
                [$method, $route] = explode(' ', $endpoint);

                $methods = [
                    'GET' => 1,
                    'POST' => 2,
                    'PUT' => 3,
                    'PATCH' => 4,
                    'DELETE' => 5,
                ];

                return explode('/', $route)[0].($methods[$method] ?? 6);
            })
            ->map(function ($requests) {
                return (object)[
                    'mainRequest' => $requests->first(),
                    'additionalRequests' => $requests->slice(1)
                ];
            });

        return view('enlighten::area.endpoints', [
            'area' => $area,
            'endpoints' => $endpoints,
        ]);
    }

    private function getArea(Run $run, string $areaSlug = null): ?Area
    {
        if (empty($areaSlug)) {
            return null;
        }

        return $run->areas->firstWhere('slug', $areaSlug);
    }

    private function getGroups(Run $run, Area $area = null): Collection
    {
        return $run->groups
            ->when($area, function ($collection, $area) {
                return $collection->where('area', $area->slug);
            });
    }

    private function getModules(Collection $groups): ModuleCollection
    {
        return Module::all()->addGroups($groups)->whereHasGroups();
    }
}
