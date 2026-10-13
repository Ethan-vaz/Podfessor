"""Run with: python -m unittest python/test_nim.py  (or: python python/test_nim.py)"""

import unittest

from nim import MAX, MIN, NimGame, State, generate_move, play_game


class NimTest(unittest.TestCase):
    def test_matches_known_solution(self):
        for stones in range(1, 17):
            with self.subTest(stones=stones):
                game = NimGame(stones)
                mm = generate_move(game, game.initial_state, "minimax")
                ab = generate_move(game, game.initial_state, "alphabeta")

                # The player to move wins iff stones % 4 != 0,
                # and the winning move leaves a multiple of 4.
                first_player_wins = stones % 4 != 0
                self.assertEqual(mm["value"], 1 if first_player_wins else -1)
                self.assertEqual(ab["value"], mm["value"])
                if first_player_wins:
                    self.assertEqual(mm["move"], stones % 4)
                    self.assertEqual(ab["move"], stones % 4)
                self.assertLessEqual(ab["nodes"], mm["nodes"])

                winner, _ = play_game(stones)
                self.assertEqual(winner, MAX if first_player_wins else MIN)

    def test_min_to_move(self):
        game = NimGame(7)
        choice = generate_move(game, State(7, MIN))
        self.assertEqual((choice["move"], choice["value"]), (3, -1))

    def test_depth_cutoff(self):
        game = NimGame(41)
        self.assertEqual(generate_move(game, game.initial_state, max_depth=4)["move"], 1)

    def test_terminal_and_invalid(self):
        game = NimGame(0)
        self.assertIsNone(generate_move(game, game.initial_state)["move"])
        with self.assertRaises(ValueError):
            NimGame(-1)


if __name__ == "__main__":
    unittest.main()
