<?php

namespace FactionsPro;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\block\Snow;
use pocketmine\math\Vector3;

class FactionMain extends PluginBase implements Listener {
	
	public $db;
	public $prefs;
	private $fCommand;
	
	public function onEnable() {
		
		@mkdir($this->getDataFolder());
		
		if(!file_exists($this->getDataFolder() . "BannedNames.txt")) {
			$file = fopen($this->getDataFolder() . "BannedNames.txt", "w");
			$txt = "Admin:admin:Staff:staff:Owner:owner:Builder:builder:Op:OP:op";
			fwrite($file, $txt);
		}
		
		$this->getServer()->getPluginManager()->registerEvents(new FactionListener($this), $this);
		$this->fCommand = new FactionCommands($this);
		
		$this->prefs = new Config($this->getDataFolder() . "Prefs.yml", CONFIG::YAML, array(
				"MaxFactionNameLength" => 20,
				"MaxPlayersPerFaction" => 10,
				"ClaimingEnabled" => true,
				"OnlyLeadersAndOfficersCanInvite" => true,
				"OfficersCanClaim" => true,
				"PlotSize" => 25,
				"Member" => array(
						"claim" => false,
						"demote" => false,
						"home" => true,
						"invite" => false,
						"kick" => false,
						"motd" => false,
						"promote" => false,
						"sethome" => false,
						"unclaim" => false,
						"unsethome" => false
				),
				"Officer" => array(
						"claim" => true,
						"demote" => false,
						"home" => true,
						"invite" => true,
						"kick" => true,
						"motd" => true,
						"promote" => false,
						"sethome" => true,
						"unclaim" => true,
						"unsethome" => true
				)
		));
		$this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS home(faction TEXT PRIMARY KEY, x INT, y INT, z INT, world VARCHAR);");
	}
		
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		return $this->fCommand->onCommand($sender, $command, $label, $args);
	}

    public function getFaction(string $player) {
        $faction = $this->db->query("SELECT faction FROM master WHERE player='$player';");
        $factionArray = $faction->fetchArray(SQLITE3_ASSOC);
        return $factionArray["faction"];
    }

	public function isInFaction(string $player) {
		$player = strtolower($player);
		$result = $this->db->query("SELECT player FROM master WHERE player='$player';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	
	public function isLeader(string $player) {
		$faction = $this->db->query("SELECT rank FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Leader";
	}
	
	public function isOfficer(string $player) {
		$faction = $this->db->query("SELECT rank FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Officer";
	}
	
	public function isMember(string $player) {
		$faction = $this->db->query("SELECT rank FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Member";
	}
	
	public function getRank(string $player) {
		$faction = $this->db->query("SELECT rank FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"];
	}
	
	public function hasPermission(string $player, $command) {
		$rank = $this->getRank($player);
		return $this->prefs->get("$rank")["$command"];
	}
	
	public function getPlayerFaction(string $player) {
		$faction = $this->db->query("SELECT faction FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["faction"];
	}
	
	public function getLeader(string $faction) {
		$leader = $this->db->query("SELECT player FROM master WHERE faction='$faction' AND rank='Leader';");
		$leaderArray = $leader->fetchArray(SQLITE3_ASSOC);
		return $leaderArray['player'];
	}
	
	public function factionExists(string $faction) {
		$result = $this->db->query("SELECT faction FROM master WHERE faction='$faction';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	
	public function sameFaction(string $player1, string $player2) {
		$faction = $this->db->query("SELECT faction FROM master WHERE player='$player1';");
		$player1Faction = $faction->fetchArray(SQLITE3_ASSOC);
		$faction = $this->db->query("SELECT faction FROM master WHERE player='$player2';");
		$player2Faction = $faction->fetchArray(SQLITE3_ASSOC);
		return $player1Faction["faction"] == $player2Faction["faction"];
	}
	
	public function getNumberOfPlayers(string $faction) {
		$query = $this->db->query("SELECT COUNT(player) as count FROM master WHERE faction='$faction';");
		$number = $query->fetchArray();
		return $number['count'];
	}
	
	public function isFactionFull(string $faction) {
		return $this->getNumberOfPlayers($faction) >= $this->prefs->get("MaxPlayersPerFaction");
	}
	
	public function isNameBanned($name) {
		$bannedNames = explode(":", file_get_contents($this->getDataFolder() . "BannedNames.txt"));
		return in_array($name, $bannedNames);
	}
	
public function newPlot(string $faction, $x1, $z1, $x2, $z2) {
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO plots (faction, x1, z1, x2, z2) VALUES (:faction, :x1, :z1, :x2, :z2);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":x1", $x1);
		$stmt->bindValue(":z1", $z1);
		$stmt->bindValue(":x2", $x2);
		$stmt->bindValue(":z2", $z2);
		$result = $stmt->execute();
	}
	public function drawPlot($sender, $faction, $x, $y, $z, $level, $size) {
		$arm = ($size - 1) / 2;
		$block = new Snow();
		if($this->cornerIsInPlot($x + $arm, $z + $arm, $x - $arm, $z - $arm)) {
			$claimedBy = $this->factionFromPoint($x, $z);
			$sender->sendMessage($this->formatMessage("This area is aleady claimed by $claimedBy."));
			return false;
		}
		$level->setBlock(new Vector3($x + $arm, $y, $z + $arm), $block);
		$level->setBlock(new Vector3($x - $arm, $y, $z - $arm), $block);
		$this->newPlot($faction, $x + $arm, $z + $arm, $x - $arm, $z - $arm);
		return true;
	}
	
	public function isInPlot(Player $player) {
		$x = $player->getFloorX();
		$z = $player->getFloorZ();
		$result = $this->db->query("SELECT faction FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
	
	public function factionFromPoint($x,$z) {
		$result = $this->db->query("SELECT faction FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return $array["faction"];
	}
	
	public function inOwnPlot(Player $player) {
		$playerName = $player->getName();
		$x = $player->getFloorX();
		$z = $player->getFloorZ();
		return $this->getPlayerFaction($playerName) == $this->factionFromPoint($x, $z);
	}
	
	public function pointIsInPlot($x,$z) {
		$result = $this->db->query("SELECT faction FROM plots WHERE $x <= x1 AND $x >= x2 AND $z <= z1 AND $z >= z2;");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return !empty($array);
	}
	
	public function cornerIsInPlot($x1, $z1, $x2, $z2) {
		return($this->pointIsInPlot($x1, $z1) || $this->pointIsInPlot($x1, $z2) || $this->pointIsInPlot($x2, $z1) || $this->pointIsInPlot($x2, $z2));
	}
	
	public function formatMessage($string, $confirm = false) {
		if($confirm) {
			return "[" . TextFormat::BLUE . "FactionsPro" . TextFormat::WHITE . "] " . TextFormat::GREEN . "$string";
		} else {	
			return "[" . TextFormat::BLUE . "FactionsPro" . TextFormat::WHITE . "] " . TextFormat::RED . "$string";
		}
	}
	
	public function motdWaiting(string $player) {
		$stmt = $this->db->query("SELECT player FROM motdrcv WHERE player='$player';");
		$array = $stmt->fetchArray(SQLITE3_ASSOC);
		return !empty($array);
	}
	
	public function getMOTDTime(string $player) {
		$stmt = $this->db->query("SELECT timestamp FROM motdrcv WHERE player='$player';");
		$array = $stmt->fetchArray(SQLITE3_ASSOC);
		return $array['timestamp'];
	}
	
	public function setMOTD(string $faction, string $player, string $msg) {
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO motd (faction, message) VALUES (:faction, :message);");
		$stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":message", $msg);
		$result = $stmt->execute();
		
		$this->db->query("DELETE FROM motdrcv WHERE player='$player';");
	}
	
	public function updateTag(string $player) {
		$p = $this->getServer()->getPlayer($player);
		if(!$this->isInFaction($player)) {
			$p->setNameTag($player);
		} elseif($this->isLeader($player)) {
			$p->setNameTag("**[" . $this->getPlayerFaction($player) . "] " . $player);
		} elseif($this->isOfficer($player)) {
			$p->setNameTag("*[" . $this->getPlayerFaction($player) . "] " . $player);
		} elseif($this->isMember($player)) {
			$p->setNameTag("[" . $this->getPlayerFaction($player) . "] " . $player);
		}
	}
	
	public function onDisable() {
		$this->db->close();
	}
}
