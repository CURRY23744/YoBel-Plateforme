<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\UserAvailabilityException;
use App\Models\UserWeeklyAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PlanningController extends ApiController
{
    private const WEEKDAYS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'integer'],
        ]);
        $me = $this->me();
        $weekStart = Carbon::parse($validated['week_start'] ?? now()->toDateString())->startOfWeek(Carbon::MONDAY);
        $user = $me;

        if (! empty($validated['user_id']) && (int) $validated['user_id'] !== $me->id) {
            abort_unless($me->isStaff(), 403, 'Seuls les administrateurs et chefs de projet peuvent consulter le planning d’un autre membre.');
            $user = User::query()
                ->where('organization_id', $me->organization_id)
                ->where('is_active', true)
                ->where('role', '!=', 'guest')
                ->findOrFail($validated['user_id']);
        }

        $response = $this->userSchedule($user, $weekStart);

        if ($me->isStaff() && empty($validated['user_id'])) {
            $response['team'] = User::query()
                ->where('organization_id', $me->organization_id)
                ->where('is_active', true)
                ->where('role', '!=', 'guest')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (User $member) => [
                    'user' => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'role' => $member->role->value,
                    ],
                    'days' => $this->userSchedule($member, $weekStart)['days'],
                ]);
        }

        return response()->json($response);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate(['week_start' => ['nullable', 'date_format:Y-m-d']]);
        $weekStart = Carbon::parse($request->input('week_start', now()->toDateString()))->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $validator = Validator::make($request->all(), [
            'week_start' => ['nullable', 'date_format:Y-m-d'],
            'weekly' => ['required', 'array', 'size:7'],
            'weekly.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'weekly.*.is_available' => ['required', 'boolean'],
            'weekly.*.start_time' => ['nullable', 'date_format:H:i'],
            'weekly.*.end_time' => ['nullable', 'date_format:H:i'],
            'exceptions' => ['required', 'array', 'max:7'],
            'exceptions.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'exceptions.*.mode' => ['required', 'in:available,unavailable'],
            'exceptions.*.start_time' => ['nullable', 'date_format:H:i'],
            'exceptions.*.end_time' => ['nullable', 'date_format:H:i'],
            'exceptions.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request, $weekStart, $weekEnd) {
            foreach ($request->input('weekly', []) as $index => $day) {
                if (! is_array($day)) {
                    continue;
                }
                if (! empty($day['is_available']) && (empty($day['start_time']) || empty($day['end_time']))) {
                    $validator->errors()->add("weekly.{$index}.start_time", 'Indiquez les heures de début et de fin pour une journée disponible.');
                } elseif (! empty($day['is_available']) && is_string($day['start_time']) && is_string($day['end_time']) && $day['start_time'] >= $day['end_time']) {
                    $validator->errors()->add("weekly.{$index}.end_time", 'L’heure de fin doit être après l’heure de début.');
                }
            }

            foreach ($request->input('exceptions', []) as $index => $exception) {
                if (! is_array($exception)) {
                    continue;
                }
                $date = $exception['date'] ?? null;
                if (is_string($date) && $date !== '' && ($date < $weekStart->toDateString() || $date > $weekEnd->toDateString())) {
                    $validator->errors()->add("exceptions.{$index}.date", 'La date doit appartenir à la semaine affichée.');
                }
                if (($exception['mode'] ?? null) === 'available' && (empty($exception['start_time']) || empty($exception['end_time']))) {
                    $validator->errors()->add("exceptions.{$index}.start_time", 'Indiquez les heures de début et de fin.');
                } elseif (($exception['mode'] ?? null) === 'available' && is_string($exception['start_time']) && is_string($exception['end_time']) && $exception['start_time'] >= $exception['end_time']) {
                    $validator->errors()->add("exceptions.{$index}.end_time", 'L’heure de fin doit être après l’heure de début.');
                }
            }
        });

        $data = $validator->validate();
        $userId = $this->me()->id;

        DB::transaction(function () use ($data, $weekStart, $userId) {
            foreach ($data['weekly'] as $day) {
                UserWeeklyAvailability::updateOrCreate(
                    ['user_id' => $userId, 'weekday' => (int) $day['weekday']],
                    [
                        'is_available' => $day['is_available'],
                        'start_time' => $day['is_available'] ? $day['start_time'] : null,
                        'end_time' => $day['is_available'] ? $day['end_time'] : null,
                    ]
                );
            }

            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
            UserAvailabilityException::query()
                ->where('user_id', $userId)
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd])
                ->delete();

            foreach ($data['exceptions'] as $exception) {
                UserAvailabilityException::create([
                    'user_id' => $userId,
                    'date' => $exception['date'],
                    'mode' => $exception['mode'],
                    'start_time' => $exception['mode'] === 'available' ? ($exception['start_time'] ?? null) : null,
                    'end_time' => $exception['mode'] === 'available' ? ($exception['end_time'] ?? null) : null,
                    'reason' => $exception['reason'] ?? null,
                ]);
            }
        });

        return response()->json($this->userSchedule($this->me(), $weekStart));
    }

    private function userSchedule(User $user, Carbon $weekStart): array
    {
        $weekly = UserWeeklyAvailability::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('weekday');
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $exceptions = UserAvailabilityException::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->keyBy(fn (UserAvailabilityException $exception) => $exception->date->format('Y-m-d'));

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $weekStart->copy()->addDays($offset);
            $weekday = $date->dayOfWeekIso;
            $base = $weekly->get($weekday);
            $exception = $exceptions->get($date->toDateString());
            $isExceptionUnavailable = $exception && $exception->mode === 'unavailable';
            $isExceptionAvailable = $exception && $exception->mode === 'available';
            $isAvailable = $isExceptionUnavailable ? false : ($isExceptionAvailable ? true : (bool) ($base?->is_available));
            $startTime = $isAvailable ? ($isExceptionAvailable ? $exception->start_time : $base?->start_time) : null;
            $endTime = $isAvailable ? ($isExceptionAvailable ? $exception->end_time : $base?->end_time) : null;

            $days[] = [
                'date' => $date->toDateString(),
                'weekday' => $weekday,
                'label' => self::WEEKDAYS[$weekday],
                'is_available' => $isAvailable,
                'is_configured' => (bool) ($exception || $base),
                'start_time' => $startTime ? substr($startTime, 0, 5) : null,
                'end_time' => $endTime ? substr($endTime, 0, 5) : null,
                'reason' => $exception?->reason,
                'source' => $exception ? 'exception' : 'weekly',
                'exception_mode' => $exception?->mode,
            ];
        }

        return [
            'user' => ['id' => $user->id, 'name' => $user->name],
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'weekly' => $weekly->map(fn (UserWeeklyAvailability $day) => [
                'weekday' => $day->weekday,
                'is_available' => $day->is_available,
                'start_time' => $day->start_time ? substr($day->start_time, 0, 5) : null,
                'end_time' => $day->end_time ? substr($day->end_time, 0, 5) : null,
            ])->values(),
            'exceptions' => $exceptions->map(fn (UserAvailabilityException $exception) => [
                'date' => $exception->date->format('Y-m-d'),
                'mode' => $exception->mode,
                'start_time' => $exception->start_time ? substr($exception->start_time, 0, 5) : null,
                'end_time' => $exception->end_time ? substr($exception->end_time, 0, 5) : null,
                'reason' => $exception->reason,
            ])->values(),
            'days' => $days,
        ];
    }
}
