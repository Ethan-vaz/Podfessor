// Nim move generator based on the Minimax and Alpha-Beta search algorithms
// (Russell & Norvig, "Artificial Intelligence: A Modern Approach", ch. 5).
//
// Rules: a single pile starts with a configurable number of stones. Players
// alternate removing 1, 2 or 3 stones. The player who removes the last stone wins.
//
// Usable in the browser (window.Nim) or Node (require("./nim.js")).

let MAX = "MAX"
let MIN = "MIN"

// ---------------------------------------------------------------------------
// Game formulation: INITIAL-STATE, PLAYER, ACTIONS, RESULT, TERMINAL-TEST, UTILITY
// ---------------------------------------------------------------------------

let createNimGame = (stones, options = {}) => {
    if (!Number.isInteger(stones) || stones < 0) {
        throw new Error("Number of stones must be a non-negative integer")
    }
    let maxTake = options.maxTake ?? 3

    let game = {
        maxTake,

        // A state records how many stones are left and whose turn it is.
        initialState: { stones, toMove: options.firstPlayer ?? MAX },

        player: (state) => state.toMove,

        // Legal moves: remove 1..maxTake stones, never more than remain.
        actions: (state) => {
            let moves = []
            for (let take = 1; take <= Math.min(maxTake, state.stones); take++) {
                moves.push(take)
            }
            return moves
        },

        result: (state, take) => ({
            stones: state.stones - take,
            toMove: state.toMove === MAX ? MIN : MAX,
        }),

        terminalTest: (state) => state.stones === 0,

        // When the pile is empty, the player to move did NOT take the last
        // stone, so they lost. Utility is from MAX's point of view.
        utility: (state) => (state.toMove === MAX ? -1 : 1),

        // Heuristic for depth-limited search (H-MINIMAX). In this game a pile
        // that is a multiple of (maxTake + 1) is lost for the player to move,
        // so this is exact; it is only used when a depth cutoff is set.
        evaluate: (state) => {
            let toMoveLoses = state.stones % (maxTake + 1) === 0
            let maxWins = toMoveLoses === (state.toMove === MIN)
            return maxWins ? 1 : -1
        },
    }
    return game
}

// Shared cutoff test: terminal state or depth limit reached.
let cutoff = (game, state, depth, maxDepth) =>
    game.terminalTest(state) || depth >= maxDepth

let score = (game, state) =>
    game.terminalTest(state) ? game.utility(state) : game.evaluate(state)

// ---------------------------------------------------------------------------
// MINIMAX-DECISION
// ---------------------------------------------------------------------------

let minimaxDecision = (game, state, options = {}) => {
    let maxDepth = options.maxDepth ?? Infinity
    let stats = { nodes: 0 }

    let maxValue = (s, depth) => {
        stats.nodes++
        if (cutoff(game, s, depth, maxDepth)) return score(game, s)
        let v = -Infinity
        for (let a of game.actions(s)) {
            v = Math.max(v, minValue(game.result(s, a), depth + 1))
        }
        return v
    }

    let minValue = (s, depth) => {
        stats.nodes++
        if (cutoff(game, s, depth, maxDepth)) return score(game, s)
        let v = Infinity
        for (let a of game.actions(s)) {
            v = Math.min(v, maxValue(game.result(s, a), depth + 1))
        }
        return v
    }

    return decide(game, state, stats, (child) =>
        game.player(state) === MAX ? minValue(child, 1) : maxValue(child, 1))
}

// ---------------------------------------------------------------------------
// ALPHA-BETA-SEARCH
// ---------------------------------------------------------------------------

let alphaBetaSearch = (game, state, options = {}) => {
    let maxDepth = options.maxDepth ?? Infinity
    let stats = { nodes: 0 }

    let maxValue = (s, alpha, beta, depth) => {
        stats.nodes++
        if (cutoff(game, s, depth, maxDepth)) return score(game, s)
        let v = -Infinity
        for (let a of game.actions(s)) {
            v = Math.max(v, minValue(game.result(s, a), alpha, beta, depth + 1))
            if (v >= beta) return v // prune: MIN will never allow this branch
            alpha = Math.max(alpha, v)
        }
        return v
    }

    let minValue = (s, alpha, beta, depth) => {
        stats.nodes++
        if (cutoff(game, s, depth, maxDepth)) return score(game, s)
        let v = Infinity
        for (let a of game.actions(s)) {
            v = Math.min(v, maxValue(game.result(s, a), alpha, beta, depth + 1))
            if (v <= alpha) return v // prune: MAX will never allow this branch
            beta = Math.min(beta, v)
        }
        return v
    }

    // The root keeps its own alpha/beta window so later root moves are pruned too.
    let alpha = -Infinity
    let beta = Infinity
    return decide(game, state, stats, (child) => {
        if (game.player(state) === MAX) {
            let v = minValue(child, alpha, beta, 1)
            alpha = Math.max(alpha, v)
            return v
        }
        let v = maxValue(child, alpha, beta, 1)
        beta = Math.min(beta, v)
        return v
    })
}

// Root of both searches: pick the action whose resulting state has the best
// value for the player to move (argmax for MAX, argmin for MIN). Ties keep the
// first (smallest) move found.
let decide = (game, state, stats, valueOf) => {
    if (game.terminalTest(state)) {
        return { move: null, value: game.utility(state), nodes: 1 }
    }
    stats.nodes++
    let isMax = game.player(state) === MAX
    let best = { move: null, value: isMax ? -Infinity : Infinity }
    for (let a of game.actions(state)) {
        let v = valueOf(game.result(state, a))
        if (isMax ? v > best.value : v < best.value) best = { move: a, value: v }
    }
    return { ...best, nodes: stats.nodes }
}

// ---------------------------------------------------------------------------
// Move generator: the public entry point.
// ---------------------------------------------------------------------------

// Returns { move, value, nodes } for the player to move.
//   algorithm: "alphabeta" (default) or "minimax"
//   maxDepth:  optional search depth limit (uses game.evaluate at the cutoff)
let generateMove = (game, state, options = {}) => {
    let search = options.algorithm === "minimax" ? minimaxDecision : alphaBetaSearch
    return search(game, state, options)
}

// Play a full game between two search-driven players and return the history.
let playGame = (stones, options = {}) => {
    let game = createNimGame(stones, options)
    let state = game.initialState
    let history = []
    while (!game.terminalTest(state)) {
        let player = game.player(state)
        let { move, value, nodes } = generateMove(game, state, options)
        history.push({ player, stones: state.stones, move, value, nodes })
        state = game.result(state, move)
    }
    // The player who just moved took the last stone.
    let winner = state.toMove === MAX ? MIN : MAX
    return { winner, history }
}

let Nim = {
    MAX,
    MIN,
    createNimGame,
    minimaxDecision,
    alphaBetaSearch,
    generateMove,
    playGame,
}

if (typeof module !== "undefined" && module.exports) {
    module.exports = Nim
}
if (typeof window !== "undefined") {
    window.Nim = Nim
}

// Command-line demo: node javascript/nim.js [stones] [minimax|alphabeta]
if (typeof require !== "undefined" && typeof module !== "undefined" && require.main === module) {
    let stones = parseInt(process.argv[2] ?? "10", 10)
    let algorithm = process.argv[3] ?? "alphabeta"
    let { winner, history } = playGame(stones, { algorithm })
    console.log(`Nim with ${stones} stones, ${algorithm} vs ${algorithm}`)
    history.forEach(({ player, stones, move, value, nodes }) => {
        console.log(`${player} sees ${stones} stones -> takes ${move} ` +
            `(value ${value > 0 ? "+1" : value}, ${nodes} nodes searched)`)
    })
    console.log(`${winner} removes the last stone and wins.`)
}
