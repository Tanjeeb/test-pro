<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlayerController extends Controller
{
    public function index()
    {
        $players = Player::with('skills')->get();
        return response()->json($players);
    }

    public function show($id)
    {
        $player = Player::with('skills')->find($id);
        if (!$player) {
            return response()->json(['error' => 'Player not found'], 404);
        }
        return response()->json($player);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->validationRules(),$this->validationMessages());

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $player = Player::create($request->only(['name', 'position']));
        $player->skills()->createMany($request->input('playerSkills'));

        return response()->json($player->load('skills'), 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), $this->validationRules(),$this->validationMessages());

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $player = Player::findOrFail($id);
        $player->update($request->only(['name', 'position']));
        $player->skills()->delete();
        $player->skills()->createMany($request->input('playerSkills'));

        return response()->json($player->load('skills'), 200);
    }

    public function destroy($id)
    {
        $player = Player::find($id);
        if (!$player) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        $player->delete();

        return response()->json(['message' => 'Player deleted'], 200);
    }

    public function processTeamSelection(Request $request)
    {
        $teamRequirements = $request->json()->all();
        $selectedPlayers = [];

        foreach ($teamRequirements as $requirement) {
            $players = $this->getPlayersForPosition($requirement['position'], $requirement['mainSkill']);
            if ($players->isEmpty()) {
                return response()->json(['error' => 'Insufficient number of players for position: ' . $requirement['position']], 404);
            }
            $selectedPlayers[] = $this->selectPlayers($players, $requirement['numberOfPlayers']);
        }

        return response()->json(array_merge(...$selectedPlayers), 200);
    }

    private function validationRules()
    {
        return [
            'name' => 'required|string|max:255',
            'position' => 'required|in:defender,midfielder,forward',
            'playerSkills' => 'required|array|min:1',
            'playerSkills.*.skill' => 'required|in:defense,attack,speed,strength,stamina',
            'playerSkills.*.value' => 'required|integer|min:0|max:100',
        ];
    }
    private function validationMessages()
    {
        return [
            'position.in' => 'Invalid value for :attribute: ' . request()->input('position'),
            'playerSkills.*.skill.in' => 'Invalid value for :attribute: ' . request()->input('playerSkills')[0]['skill'],
            'playerSkills.*.value.*' => 'Invalid value for :attribute: ' . request()->input('playerSkills')[0]['value'],
        ];
    }
    private function getPlayersForPosition($position, $mainSkill)
    {
        return Player::with(['skills' => function ($query) use ($mainSkill) {
            $query->where('skill', $mainSkill);
        }])
            ->where('position', $position)
            ->get();
    }

    private function selectPlayers($players, $numberOfPlayers)
    {
        return $players->sortByDesc(function ($player) {
            return $player->skills->isEmpty() ? 0 : $player->skills->first()->value;
        })->take($numberOfPlayers)->toArray();
    }
}
