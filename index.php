<?php

enum WORM_DIRECTION: int
{
    case UP = 0;
    case RIGHT = 1;
    case DOWN = 3;
    case LEFT = 4;
}

class Worm
{
    public $headRow;
    public $headCol;
    public int $length;
    public WORM_DIRECTION $movingDirection = WORM_DIRECTION::RIGHT;
    private $wormMem = [];

    private $xRow;
    private $xCol;

    public function __construct($row, $col, $length = 5)
    {
        $this->headRow = $row;
        $this->headCol = $col;
        $this->length  = $length;
    }

    private function matchCoordinates(WORM_DIRECTION $direction): array
    {
        return match ($direction) {
            WORM_DIRECTION::UP => [-1, 0],
            WORM_DIRECTION::DOWN => [1, 0],
            WORM_DIRECTION::LEFT => [0, -1],
            WORM_DIRECTION::RIGHT => [0, 1],
        };
    }

    private function getHeadSym(WORM_DIRECTION $direction): string
    {
        return match ($direction) {
            WORM_DIRECTION::UP => '^',
            WORM_DIRECTION::DOWN => 'v',
            WORM_DIRECTION::LEFT => '<',
            WORM_DIRECTION::RIGHT => '>',
        };
    }
    private function _updateDirection(WORM_DIRECTION $direction): void
    {
        $this->movingDirection = $direction;
    }

    public function changeDirection(WORM_DIRECTION $direction): void
    {
        $directionPair = [$direction, $this->movingDirection];
        match ($directionPair) {
            [WORM_DIRECTION::LEFT, WORM_DIRECTION::RIGHT] => null,
            [WORM_DIRECTION::RIGHT, WORM_DIRECTION::LEFT] => null,
            [WORM_DIRECTION::UP, WORM_DIRECTION::DOWN] => null,
            [WORM_DIRECTION::DOWN, WORM_DIRECTION::UP] => null,
            default => $this->_updateDirection($direction)
        };
    }

    public function showX(&$screenBuffer)
    {
        if (isset($screenBuffer[$this->xRow][$this->xCol]) && $screenBuffer[$this->xRow][$this->xCol] === ' ') {
            $screenBuffer[$this->xRow][$this->xCol] = 'X';
            return;
        }
        // Generate other X
        while (true) {
            $xRow = rand(2, GameState::SCREEN_HEIGHT-2);
            $xCol = rand(2, GameState::SCREEN_WIDTH-2);

            if ($screenBuffer[$xRow][$xCol] === ' ') {
                $screenBuffer[$xRow][$xCol] = 'X';
                $this->xRow = $xRow;
                $this->xCol = $xCol;
                break;
            }
        }
    }

    public function move(&$screenBuffer) {
        $this->showX($screenBuffer);
        $coords = $this->matchCoordinates($this->movingDirection);
        $this->headRow = ($this->headRow + $coords[0]) % GameState::SCREEN_HEIGHT;
        if ($this->headRow < 0) $this->headRow = GameState::SCREEN_HEIGHT-1;
        $this->headCol = ($this->headCol + $coords[1]) % GameState::SCREEN_WIDTH;
        if ($this->headCol < 0) $this->headCol = GameState::SCREEN_WIDTH-1;

        if ($screenBuffer[$this->headRow][$this->headCol] === 'X') {
            $this->length++;
            $this->showX($screenBuffer);
        }

        array_unshift($this->wormMem, [$this->headRow, $this->headCol]);
        $this->wormMem = array_slice($this->wormMem, 0, $this->length);
        foreach ($this->wormMem as $k => [$row, $col]) {
            if ($k === 0) {
                $screenBuffer[$row][$col] = $this->getHeadSym($this->movingDirection);
                continue;
            }
            $screenBuffer[$row][$col] = 'O';
        }
    }
}

class GameState
{
    const SCREEN_HEIGHT = 32;
    const SCREEN_WIDTH = 80;
    const FRAME_RATE = 10;

    private $screenBuffer = [];

    private $stack = [];

    private Worm $worm;

    /**
     * @var true
     */
    private bool $isGameRunning = false;

    public function __construct()
    {
        $this->reset();
    }

    private function reset()
    {
        $this->clearScreenBuffer();
        $this->renderHelperScreen();
        $this->stack = [];
        $this->worm  = new Worm(30, 30, 1);
    }

    function clearScreenBuffer()
    {
        $this->screenBuffer = array_fill(0, self::SCREEN_HEIGHT, '||' . str_repeat(' ', self::SCREEN_WIDTH) . '||');
        $this->screenBuffer[0] = str_repeat('=', self::SCREEN_WIDTH+2);
        $this->screenBuffer[self::SCREEN_HEIGHT-1] = str_repeat('=', self::SCREEN_WIDTH+4);
    }

    public function renderHelperScreen()
    {
        $this->isGameRunning = false;
        $this->stack[]     = $this->screenBuffer;
        $instructionBuffer = [
            "How to play:",
            "  Moving the snack around with keys: ",
            "    (h): Left    (l): Right    (j) Up    (k) Down",
            "  To start the game press any key above.",
            "Press (?) for showing help.",
            "Press (b) to go gack.",
            "Press (c) to clean screen.",
            "(q) for quit.",
        ];

        $this->screenBuffer = array_merge(
            array_slice($this->screenBuffer, 0, self::SCREEN_HEIGHT - count($instructionBuffer)),
            $instructionBuffer
        );
    }

    function changeDirection(WORM_DIRECTION $direction): void
    {
        if ($this->isGameRunning === false) {
            $this->isGameRunning = true;
            $this->clearScreenBuffer();
            $this->worm->showX($this->screenBuffer);
        }

        $this->worm->changeDirection($direction);
    }

    public function back(): void
    {
        if (count($this->stack) > 0) {
            $this->screenBuffer = array_pop($this->stack);
        } else {
            $this->clearScreenBuffer();
        }
    }

    public function pressKey($k)
    {
        match ($k) {
            '?' => $this->renderHelperScreen(),
            'q' => $this->quit(),
            'b' => $this->back(),
            'c' => $this->clearScreenBuffer(),

            'h' => $this->changeDirection(WORM_DIRECTION::LEFT),
            'j' => $this->changeDirection(WORM_DIRECTION::UP),
            'k' => $this->changeDirection(WORM_DIRECTION::DOWN),
            'l' => $this->changeDirection(WORM_DIRECTION::RIGHT),
            default => null,
        };
    }

    public function render()
    {
        $scoreString = sprintf('Score: %d', $this->worm->length);
        $this->screenBuffer[0] = $scoreString. str_repeat('=', self::SCREEN_WIDTH + 4 - strlen($scoreString));
        foreach ($this->screenBuffer as $line) {
            printf("%s\n", $line);
        }
    }

    public function tick()
    {
        if ($this->isGameRunning) {
            $this->clearScreenBuffer();
            $this->worm->move($this->screenBuffer);
        }

        $this->render();
        printf("[%d, %d]", $this->worm->headRow, $this->worm->headCol);
        usleep(1000000/self::FRAME_RATE);
    }

    private function quit()
    {
        echo "Bye Bye!\n";
        exit(0);
    }
}

$game = new GameState();

system('stty cbreak -echo');
$stdIn  = STDIN;
$stdOut = STDOUT;

stream_set_blocking($stdIn, 0);

while (!feof($stdIn)) {
    // Handle Key Press
    $keyPress = fgets($stdIn);
    $game->pressKey($keyPress);
    system('clear');
    $game->tick();
}