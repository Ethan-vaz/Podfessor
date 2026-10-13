"""Nim move generator based on the Minimax and Alpha-Beta search algorithms
(Russell & Norvig, "Artificial Intelligence: A Modern Approach", ch. 5).

Rules: a single pile starts with a configurable number of stones. Players
alternate removing 1, 2 or 3 stones. The player who removes the last stone wins.

Command-line demo: python nim.py [stones] [minimax|alphabeta]
"""

import math
import sys
from dataclasses import dataclass

MAX = "MAX"
MIN = "MIN"


@dataclass(frozen=True)
class State:
    stones: int
    to_move: str = MAX


# ---------------------------------------------------------------------------
# Game formulation: INITIAL-STATE, PLAYER, ACTIONS, RESULT, TERMINAL-TEST, UTILITY
# ---------------------------------------------------------------------------

class NimGame:
    def __init__(self, stones, max_take=3, first_player=MAX):
        if not isinstance(stones, int) or stones < 0:
            raise ValueError("Number of stones must be a non-negative integer")
        self.max_take = max_take
        self.initial_state = State(stones, first_player)

    def player(self, state):
        return state.to_move

    def actions(self, state):
        """Legal moves: remove 1..max_take stones, never more than remain."""
        return list(range(1, min(self.max_take, state.stones) + 1))

    def result(self, state, take):
        return State(state.stones - take, MIN if state.to_move == MAX else MAX)

    def terminal_test(self, state):
        return state.stones == 0

    def utility(self, state):
        """When the pile is empty, the player to move did NOT take the last
        stone, so they lost. Utility is from MAX's point of view."""
        return -1 if state.to_move == MAX else 1

    def evaluate(self, state):
        """Heuristic for depth-limited search (H-MINIMAX). A pile that is a
        multiple of (max_take + 1) is lost for the player to move, so this is
        exact; it is only used when a depth cutoff is set."""
        to_move_loses = state.stones % (self.max_take + 1) == 0
        max_wins = to_move_loses == (state.to_move == MIN)
        return 1 if max_wins else -1


def _cutoff(game, state, depth, max_depth):
    """Shared cutoff test: terminal state or depth limit reached."""
    return game.terminal_test(state) or depth >= max_depth


def _score(game, state):
    return game.utility(state) if game.terminal_test(state) else game.evaluate(state)


def _decide(game, state, stats, value_of):
    """Root of both searches: pick the action whose resulting state has the
    best value for the player to move (argmax for MAX, argmin for MIN). Ties
    keep the first (smallest) move found."""
    if game.terminal_test(state):
        return {"move": None, "value": game.utility(state), "nodes": 1}
    stats["nodes"] += 1
    is_max = game.player(state) == MAX
    best_move, best_value = None, -math.inf if is_max else math.inf
    for a in game.actions(state):
        v = value_of(game.result(state, a))
        if (v > best_value) if is_max else (v < best_value):
            best_move, best_value = a, v
    return {"move": best_move, "value": best_value, "nodes": stats["nodes"]}


# ---------------------------------------------------------------------------
# MINIMAX-DECISION
# ---------------------------------------------------------------------------

def minimax_decision(game, state, max_depth=math.inf):
    stats = {"nodes": 0}

    def max_value(s, depth):
        stats["nodes"] += 1
        if _cutoff(game, s, depth, max_depth):
            return _score(game, s)
        v = -math.inf
        for a in game.actions(s):
            v = max(v, min_value(game.result(s, a), depth + 1))
        return v

    def min_value(s, depth):
        stats["nodes"] += 1
        if _cutoff(game, s, depth, max_depth):
            return _score(game, s)
        v = math.inf
        for a in game.actions(s):
            v = min(v, max_value(game.result(s, a), depth + 1))
        return v

    root_is_max = game.player(state) == MAX
    return _decide(game, state, stats,
                   lambda child: min_value(child, 1) if root_is_max else max_value(child, 1))


# ---------------------------------------------------------------------------
# ALPHA-BETA-SEARCH
# ---------------------------------------------------------------------------

def alpha_beta_search(game, state, max_depth=math.inf):
    stats = {"nodes": 0}

    def max_value(s, alpha, beta, depth):
        stats["nodes"] += 1
        if _cutoff(game, s, depth, max_depth):
            return _score(game, s)
        v = -math.inf
        for a in game.actions(s):
            v = max(v, min_value(game.result(s, a), alpha, beta, depth + 1))
            if v >= beta:
                return v  # prune: MIN will never allow this branch
            alpha = max(alpha, v)
        return v

    def min_value(s, alpha, beta, depth):
        stats["nodes"] += 1
        if _cutoff(game, s, depth, max_depth):
            return _score(game, s)
        v = math.inf
        for a in game.actions(s):
            v = min(v, max_value(game.result(s, a), alpha, beta, depth + 1))
            if v <= alpha:
                return v  # prune: MAX will never allow this branch
            beta = min(beta, v)
        return v

    # The root keeps its own alpha/beta window so later root moves are pruned too.
    window = {"alpha": -math.inf, "beta": math.inf}
    root_is_max = game.player(state) == MAX

    def value_of(child):
        if root_is_max:
            v = min_value(child, window["alpha"], window["beta"], 1)
            window["alpha"] = max(window["alpha"], v)
        else:
            v = max_value(child, window["alpha"], window["beta"], 1)
            window["beta"] = min(window["beta"], v)
        return v

    return _decide(game, state, stats, value_of)


# ---------------------------------------------------------------------------
# Move generator: the public entry point.
# ---------------------------------------------------------------------------

def generate_move(game, state, algorithm="alphabeta", max_depth=math.inf):
    """Return {"move", "value", "nodes"} for the player to move.

    algorithm: "alphabeta" (default) or "minimax"
    max_depth: optional search depth limit (uses game.evaluate at the cutoff)
    """
    search = minimax_decision if algorithm == "minimax" else alpha_beta_search
    return search(game, state, max_depth)


def play_game(stones, algorithm="alphabeta", max_depth=math.inf, **game_options):
    """Play a full game between two search-driven players."""
    game = NimGame(stones, **game_options)
    state = game.initial_state
    history = []
    while not game.terminal_test(state):
        choice = generate_move(game, state, algorithm, max_depth)
        history.append({"player": game.player(state), "stones": state.stones, **choice})
        state = game.result(state, choice["move"])
    # The player who just moved took the last stone.
    winner = MIN if state.to_move == MAX else MAX
    return winner, history


if __name__ == "__main__":
    stones = int(sys.argv[1]) if len(sys.argv) > 1 else 10
    algorithm = sys.argv[2] if len(sys.argv) > 2 else "alphabeta"
    winner, history = play_game(stones, algorithm)
    print(f"Nim with {stones} stones, {algorithm} vs {algorithm}")
    for turn in history:
        value = "+1" if turn["value"] > 0 else turn["value"]
        print(f"{turn['player']} sees {turn['stones']} stones -> takes {turn['move']} "
              f"(value {value}, {turn['nodes']} nodes searched)")
    print(f"{winner} removes the last stone and wins.")
