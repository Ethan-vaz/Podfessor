// Run with: node tests/nim.test.js
let assert = require("node:assert")
let Nim = require("../javascript/nim.js")

for (let stones = 1; stones <= 16; stones++) {
    let game = Nim.createNimGame(stones)
    let mm = Nim.generateMove(game, game.initialState, { algorithm: "minimax" })
    let ab = Nim.generateMove(game, game.initialState, { algorithm: "alphabeta" })

    // Known solution: the player to move wins iff stones % 4 !== 0,
    // and the winning move leaves a multiple of 4.
    let firstPlayerWins = stones % 4 !== 0
    assert.strictEqual(mm.value, firstPlayerWins ? 1 : -1, `minimax value, ${stones} stones`)
    assert.strictEqual(ab.value, mm.value, `alpha-beta agrees with minimax, ${stones} stones`)
    if (firstPlayerWins) {
        assert.strictEqual(mm.move, stones % 4, `minimax move, ${stones} stones`)
        assert.strictEqual(ab.move, stones % 4, `alpha-beta move, ${stones} stones`)
    }
    assert.ok(ab.nodes <= mm.nodes, `alpha-beta searches no more nodes, ${stones} stones`)

    let { winner } = Nim.playGame(stones)
    assert.strictEqual(winner, firstPlayerWins ? Nim.MAX : Nim.MIN, `winner, ${stones} stones`)
}

// Search from MIN's perspective and with a depth cutoff.
let game = Nim.createNimGame(7)
let minState = { stones: 7, toMove: Nim.MIN }
assert.deepStrictEqual(
    [Nim.generateMove(game, minState).move, Nim.generateMove(game, minState).value], [3, -1])
let deep = Nim.createNimGame(41)
assert.strictEqual(Nim.generateMove(deep, deep.initialState, { maxDepth: 4 }).move, 1)

// Terminal and invalid input.
assert.strictEqual(Nim.generateMove(Nim.createNimGame(0), { stones: 0, toMove: Nim.MAX }).move, null)
assert.throws(() => Nim.createNimGame(-1))

console.log("All Nim tests passed")
