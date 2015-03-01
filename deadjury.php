<?php
define('DEADJURY_FILES_PREFIX', 'deadjury-game-');

if(isset($_POST['reset']))
{
    $game = $_POST['game'];
    foreach(glob('{'.DEADJURY_FILES_PREFIX'}'.$game.'*txt', GLOB_BRACE) as $filename)
    {
        unlink($filename);
    }
    $json['response'] = 1;
}
elseif(isset($_POST['guess']))
{
    $guess  = intval($_POST['guess']);
    $number = $_POST['number'];
    $game   = $_POST['game'];
    $player = $_POST['player'];
    $new    = setGame($guess, $number, $game);
    if($new)
    {
        $json = array(
            'response' => 1,
            'game'     => $new['game'],
            'player'   => $new['player']
        );
        canGuess($new['game'], $new['player'], 0);
        canGuess($new['game'], $new['player'] == 1 ? 2 : 1);
    }
    else
    {
        $filename = getFile($game, $player, $number);
        if($filename)
        {
            file_put_contents($filename, $guess);
            canGuess($game, $player, 0);
            canGuess($game, $player == 1 ? 2 : 1);
            $json['response'] = 1;
        }
    }
    if(!isset($json))
    {
        $json['response'] = 0;
    }
}
elseif(isset($_GET['number']))
{
    $number  = intval($_GET['number']);
    $game    = $_GET['game'];
    $player  = $_GET['player'];
    $guess   = getGuess($game, $player, $number);
    $allowed = isAllowedToGuess($game, $player);
    if($guess)
    {
        $opponentNumber = getOpponentNumber($game, $player == 1 ? 2 : 1);
        if($opponentNumber)
        {
            $results = compare($guess, $opponentNumber);
            $winner  = getWinner($game);
            if($results)
            {
                $json = array(
                    'response' => 1,
                    'allowed'  => $allowed,
                    'results'  => $results
                );
                if($winner)
                {
                    $json['winner'] = $winner;
                }
                elseif($results['dead'] == 4)
                {
                    setWinner($game, $number);
                    $json['winner'] = $number;
                }
            }
        }
    }
}
if(!isset($json))
{
    $json['response'] = 0;
}

echo json_encode($json);

/**************************************
***************Functions***************
***************************************/

/**
 * Gets the file associated to a player
 *
 * @param string $gameId, the Id of the game
 * @param string $playerId, the Id of the player
 * @param string $number, the number chosen by the player
 *
 * @return string|false
 */
function getFile($game, $player, $number)
{
    $filename = DEADJURY_FILES_PREFIX.$game.'-'.$player.'-'.$number.'.txt';

    return is_file($filename) ? $filename : false;
}

/**
 * Gets the last guess of the player
 *
 * @param string $game, the Id of the game
 * @param string $player, the Id of the player
 * @param string $number, the number chosen by the player
 *
 * @return string|false
 */
function getGuess($game, $player, $number)
{
    $filename = DEADJURY_FILES_PREFIX.$game.'-'.$player.'-'.$number.'.txt';

    return is_file($filename) ? trim(file_get_contents($filename)) : false;
}

/**
 * Gets the opponent's number
 *
 * @param string $game, the Id of the game
 * @param string $player, the Id of the player
 *
 * @return string|false
 */
function getOpponentNumber($game, $player)
{
    $search = glob('{'.DEADJURY_FILES_PREFIX.'}'.$game.'-'.$player.'*.txt', GLOB_BRACE);
    foreach($search as $filename) {
        if(preg_match('/([0-9]{4})\.txt$/', $filename, $matches))
        {
            return $matches[1];
        }
    }

    return false;
}

/**
 * Gets the winner of the game
 *
 * @param string $game, the Id of the game
 *
 * @return string|false
 */
function getWinner($game)
{
    $filename = DEADJURY_FILES_PREFIX.$game.'-winner.txt';

    return is_file($filename) ? trim(file_get_contents($filename)) : false;
}

/**
 * Sets the winner of the game
 *
 * @param string $game, the Id of the game
 * @param string $number, the winner's number
 */
function setWinner($game, $number)
{
    $filename = DEADJURY_FILES_PREFIX.$game.'-winner.txt';

    file_put_contents($filename, $number);
}

/**
 * Determines if the player if allowed to play
 *
 * @param string $game, the Id of the game
 * @param string $player, the Id of the player
 *
 * @return string|false
 */
function isAllowedToGuess($game, $player)
{
    $filename = DEADJURY_FILES_PREFIX.$game.'-'.$player.'-guess.txt';

    return is_file($filename) ? intval(file_get_contents($filename)) : false;
}

/**
 * Sets a file to allow the player to input his guess
 *
 * @param string $game, the Id of the game
 * @param string $player, the Id of the player
 * @param integer $canGuess, 1 => can guess, 2 => can't guess
 *
 * @return boolean
 */
function canGuess($game, $player, $canGuess = 1)
{
    $filename = DEADJURY_FILES_PREFIX.$game.'-'.$player.'-guess.txt';

    file_put_contents($filename, $canGuess);
}

/**
 * Compares a guess to the number chosen by the opponent
 *
 * @param string $guess, the number guessed by the player
 * @param string $to, the number chosen by the opponent
 *
 * @return array
 */
function compare($guess, $to)
{
    $dead    = 0;
    $injured = 0;

    for($i = 0; $i < strlen($guess); $i++)
    {
        if($guess[$i] == $to[$i])
        {
            $dead++;
        }
        elseif(strpos($to, $guess[$i]))
        {
            $injured++;
        }
    }

    return array(
        'dead'    => $dead,
        'injured' => $injured
    );
}

/**
 * Creates the file associated to a given player
 * deadjury-game-[gameId]-[playerId]-[chosenNumber].txt
 *
 * @param string $guess, the number guessed by the player
 * @param string $number, the number chosen by the player
 * @param string $game, the id of the game
 */
function setGame($guess = '', $number = '', $gameId = '')
{
    if(!$gameId)
    {
        $games   = glob('{'.DEADJURY_FILES_PREFIX.'}*.txt', GLOB_BRACE);
        $current = 0; // The newest game
        $player  = 1; // The number of players in that game
        foreach($games as $game)
        {
            if(preg_match('/game-([0-9]+).+[0-9]{4}\.txt$/', $game, $matches))
            {
                $num = intval($matches[1]);
                if($num > $current)
                {
                    $current = $num;
                    $player  = 2;
                }
                elseif($num == $current)
                {
                    $player = 1;
                }
            }
        }
        if($player == 1)
        {
            $current++;
        }
        file_put_contents(DEADJURY_FILES_PREFIX.$current.'-'.$player.'-'.$number.'.txt', $guess);

        return array(
            'game'   => $current,
            'player' => $player
        );
    }

    return false;
}

?>