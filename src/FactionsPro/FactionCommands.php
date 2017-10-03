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
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\Position;

class FactionCommands {

	public $plugin;

	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($sender instanceof Player) {
			$playerName = $sender->getPlayer()->getName();
			if (strtolower($command->getName()) === "f") {
				if(empty($args)) {
					$sender->sendMessage($this->plugin->formatMessage("Please use /f help for a list of commands"));
					return true;
				}

				/////////////////////////////// CREATE ///////////////////////////////

				if($args[0] == "create") {
					if(!isset($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Usage: /f create <faction name>"));
						return true;
					}
					if(!($this->alphanum($args[1]))) {
						$sender->sendMessage($this->plugin->formatMessage("You may only use letters and numbers!"));
						return true;
					}
					if($this->plugin->isNameBanned($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("This name is not allowed."));
						return true;
					}
					if($this->plugin->factionExists($args[1]) == true ) {
						$sender->sendMessage($this->plugin->formatMessage("Faction already exists"));
						return true;
					}
					if(strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
						$sender->sendMessage($this->plugin->formatMessage("This name is too long. Please try again!"));
						return true;
					}
					if($this->plugin->isInFaction($sender->getName())) {
						$sender->sendMessage($this->plugin->formatMessage("You must leave this faction first"));
						return true;
					} else {
						$factionName = $args[1];
						$playername = strtolower($playerName);
						$rank = "Leader";
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", $playername);
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", $rank);
						$result = $stmt->execute();
						if($this->plugin->prefs->get("FactionNametags")) {
							$this->plugin->updateTag($playername);
						}
						$sender->sendMessage($this->plugin->formatMessage("Faction successfully created!", true));
						return true;
					}
				}

				/////////////////////////////// INVITE ///////////////////////////////

				if($args[0] == "invite") {
					if(!isset($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Usage: /f invite <player>"));
						return true;
					}
					if(!$this->plugin->isInFaction($playerName)) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this"));
						return true;
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "invite")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					if( $this->plugin->isFactionFull($this->plugin->getPlayerFaction($playerName)) ) {
						$sender->sendMessage($this->plugin->formatMessage("Faction is full. Please kick players to make room."));
						return true;
					}
					$invited = $this->plugin->getServer()->getPlayerExact($args[1]);
					if($this->plugin->isInFaction($invited) == true) {
						$sender->sendMessage($this->plugin->formatMessage("Player is currently in a faction"));
						return true;
					}
					if(!$invited instanceof Player) {
						$sender->sendMessage($this->plugin->formatMessage("Player not online!"));
						return true;
					}
					$factionName = $this->plugin->getPlayerFaction($playerName);
					$invitedName = $invited->getName();
					$rank = "Member";

					$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
					$stmt->bindValue(":player", strtolower($invitedName));
					$stmt->bindValue(":faction", $factionName);
					$stmt->bindValue(":invitedby", $sender->getName());
					$stmt->bindValue(":timestamp", time());
					$result = $stmt->execute();

					$sender->sendMessage($this->plugin->formatMessage("$invitedName has been invited!", true));
					$invited->sendMessage($this->plugin->formatMessage("You have been invited to $factionName. Type '/f accept' or '/f deny' into chat to accept or deny!", true));
				}

				/////////////////////////////// LEADER ///////////////////////////////

				if($args[0] == "leader") {
					if(!isset($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Usage: /f leader <player>"));
						return true;
					}
					if(!$this->plugin->isInFaction($sender->getName())) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
						return true;
					}
					if(!$this->plugin->isLeader($playerName)) {
						$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
						return true;
					}
					if($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Add player to faction first!"));
						return true;
					}
					if(!$this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player) {
						$sender->sendMessage($this->plugin->formatMessage("Player not online!"));
						return true;
					}
					$factionName = $this->plugin->getPlayerFaction($playerName);
					$factionName = $this->plugin->getPlayerFaction($playerName);

					$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
					$stmt->bindValue(":player", $playerName);
					$stmt->bindValue(":faction", $factionName);
					$stmt->bindValue(":rank", "Member");
					$result = $stmt->execute();

					$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
					$stmt->bindValue(":player", strtolower($args[1]));
					$stmt->bindValue(":faction", $factionName);
					$stmt->bindValue(":rank", "Leader");
					$result = $stmt->execute();


					$sender->sendMessage($this->plugin->formatMessage("You are no longer leader!", true));
					$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("You are now leader \nof $factionName!",  true));
					if($this->plugin->prefs->get("FactionNametags")) {
						$this->plugin->updateTag($sender->getName());
						$this->plugin->updateTag($this->plugin->getServer()->getPlayer($args[1])->getName());
					}
				}

				/////////////////////////////// PROMOTE ///////////////////////////////

				if($args[0] == "promote") {
					if(!isset($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Usage: /f promote <player>"));
						return true;
					}
					if(!$this->plugin->isInFaction($playerName)) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
						return true;
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "promote")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					if($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
						return true;
					}
					if($this->plugin->isOfficer($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Player is already Officer"));
						return true;
					}
					$factionName = $this->plugin->getPlayerFaction($playerName);
					$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
					$stmt->bindValue(":player", strtolower($args[1]));
					$stmt->bindValue(":faction", $factionName);
					$stmt->bindValue(":rank", "Officer");
					$stmt->execute();
					$sender->sendMessage($this->plugin->formatMessage("" . $args[1] . " has been promoted to Officer!", true));
					if($promotee = $this->plugin->getServer()->getPlayer($args[1])) {
						$promotee->sendMessage($this->plugin->formatMessage("You are now Officer!", true));
					}
					if($this->plugin->prefs->get("FactionNametags")) {
						$this->plugin->updateTag($promotee->getName());
					}
				}

				/////////////////////////////// DEMOTE ///////////////////////////////

				if($args[0] == "demote") {
					if(!isset($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Usage: /f demote <player>"));
						return true;
					}
					if($this->plugin->isInFaction($sender->getName()) == false) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
						return true;
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "demote")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					if($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
						return true;
					}
					if(!$this->plugin->isOfficer($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Player is already Member"));
						return true;
					}
					$factionName = $this->plugin->getPlayerFaction($playerName);
					$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
					$stmt->bindValue(":player", strtolower($args[1]));
					$stmt->bindValue(":faction", $factionName);
					$stmt->bindValue(":rank", "Member");
					$stmt->execute();
					$sender->sendMessage($this->plugin->formatMessage("" . $args[1] . " has been demoted to Member.", true));

					if($demotee = $this->plugin->getServer()->getPlayer($args[1])) {
						$demotee->sendMessage($this->plugin->formatMessage("You were demoted to Member.", true));
					}
					if($this->plugin->prefs->get("FactionNametags")) {
						$this->plugin->updateTag($demotee->getName());
					}
				}

				/////////////////////////////// KICK ///////////////////////////////

				if($args[0] == "kick") {
					if(!isset($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Usage: /f kick <player>"));
						return true;
					}
					if($this->plugin->isInFaction($sender->getName()) == false) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
						return true;
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "kick")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					if($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Player is not in this faction!"));
						return true;
					}
					$kicked = $this->plugin->getServer()->getPlayer($args[1]);
					$factionName = $this->plugin->getPlayerFaction($playerName);
					$this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
					$sender->sendMessage($this->plugin->formatMessage("You successfully kicked $args[1]!", true));
					$players[] = $this->plugin->getServer()->getOnlinePlayers();
					if(in_array($args[1], $players) == true) {
						$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("You have been kicked from \n $factionName!", true));
						if($this->plugin->prefs->get("FactionNametags")) {
							$this->plugin->updateTag($args[1]);
						}
						return true;
					}
				}

				if (strtolower($args[0] == "forceunclaim")) {
					if (!isset($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Usage: /f forceunclaim <faction>"));
						return true;
					}
					if (!$this->plugin->factionExists($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist"));
						return true;
					}
					if (!($sender->isOp())) {
						$sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
						return true;
					}
					$sender->sendMessage($this->plugin->formatMessage("Successfully unclaimed all plots of $args[1]"));
					$this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
				}
				if (strtolower($args[0]) == 'forcedelete') {
					if (!isset($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("Usage: /f forcedelete <faction>"));
						return true;
					}
					if (!$this->plugin->factionExists($args[1])) {
						$sender->sendMessage($this->plugin->formatMessage("The requested faction doesn't exist."));
						return true;
					}
					if (!($sender->isOp())) {
						$sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
						return true;
					}
					$this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
					$this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
					$this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
					$this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
					$sender->sendMessage($this->plugin->formatMessage("Unwanted faction was successfully deleted and their faction plots unclaimed!", true));
				}

				if (strtolower($args[0]) == 'plotinfo') {
					$x = floor($sender->getX());
					$y = floor($sender->getY());
					$z = floor($sender->getZ());
					if (!$this->plugin->isInPlot($sender)) {
						$sender->sendMessage($this->plugin->formatMessage("This plot is not claimed by anyone. You can claim it by typing /f claim", true));
						return true;
					}

					$fac = $this->plugin->factionFromPoint($x, $z);
					$sender->sendMessage($this->plugin->formatMessage("This plot is claimed by $fac"));
				}

				/////////////////////////////// CLAIM ///////////////////////////////

				if(strtolower($args[0]) == "claim") {
					if($this->plugin->prefs->get("ClaimingEnabled") == false) {
						$sender->sendMessage($this->plugin->formatMessage("Plots are not enabled on this server."));
						return true;
					}
					if(!$this->plugin->isInFaction($playerName)) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction."));
						return true;
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "claim")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					if($this->plugin->inOwnPlot($sender)) {
						$sender->sendMessage($this->plugin->formatMessage("Your faction has already claimed this area."));
						return true;
					}
					$x = floor($sender->getX());
					$y = floor($sender->getY());
					$z = floor($sender->getZ());
					$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
					if(!$this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize"))) {
						return true;
					}
					$sender->sendMessage($this->plugin->formatMessage("Plot claimed.", true));
				}

				/////////////////////////////// UNCLAIM ///////////////////////////////

				if(strtolower($args[0]) == "unclaim") {
					if($this->plugin->prefs->get("ClaimingEnabled") == false) {
						$sender->sendMessage($this->plugin->formatMessage("Faction Plots are not enabled on this server."));
						return true;
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "unclaim")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					$faction = $this->plugin->getPlayerFaction($sender->getName());
					$this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
					$sender->sendMessage($this->plugin->formatMessage("Plots unclaimed.", true));
				}

				/////////////////////////////// MOTD ///////////////////////////////

				if(strtolower($args[0]) == "motd") {
					if($this->plugin->isInFaction($sender->getName()) == false) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to use this!"));
						return true;
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "motd")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					$sender->sendMessage($this->plugin->formatMessage("Type your message in chat. It will not be visible to other players", true));
					$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
					$stmt->bindValue(":player", strtolower($sender->getName()));
					$stmt->bindValue(":timestamp", time());
					$result = $stmt->execute();
				}

				/////////////////////////////// ACCEPT ///////////////////////////////

				if(strtolower($args[0]) == "accept") {
					$lowercaseName = strtolower($playerName);
					$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
					$array = $result->fetchArray(SQLITE3_ASSOC);
					if(empty($array) == true) {
						$sender->sendMessage($this->plugin->formatMessage("You have not been invited to any factions!"));
						return true;
					}
					$invitedTime = $array["timestamp"];
					$currentTime = time();
					if(($currentTime - $invitedTime) <= 60) { //This should be configurable
						$faction = $array["faction"];
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", strtolower($playerName));
						$stmt->bindValue(":faction", $faction);
						$stmt->bindValue(":rank", "Member");
						$result = $stmt->execute();
						$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
						$sender->sendMessage($this->plugin->formatMessage("You successfully joined $faction!", true));
						if($this->plugin->getServer()->getPlayer($array["invitedby"])) {
							if($this->plugin->getServer()->getPlayer($array["invitedby"])) {
								$this->plugin->getServer()->getPlayer($array["invitedby"])->sendMessage($this->plugin->formatMessage("$playerName joined the faction!", true));
							}
						}
						if($this->plugin->prefs->get("FactionNametags")) {
							$this->plugin->updateTag($sender->getName());
						}
					} else {
						$sender->sendMessage($this->plugin->formatMessage("Invite has timed out!"));
						$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
					}
				}

				/////////////////////////////// DENY ///////////////////////////////

				if(strtolower($args[0]) == "deny") {
					$lowercaseName = strtolower($playerName);
					$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
					$array = $result->fetchArray(SQLITE3_ASSOC);
					if(empty($array) == true) {
						$sender->sendMessage($this->plugin->formatMessage("You have not been invited to any factions!"));
						return true;
					}
					$invitedTime = $array["timestamp"];
					$currentTime = time();
					if( ($currentTime - $invitedTime) <= 60 ) { //This should be configurable
						$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
						$sender->sendMessage($this->plugin->formatMessage("Invite declined!", true));
						$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$playerName declined the invite!"));
					} else {
						$sender->sendMessage($this->plugin->formatMessage("Invite has timed out!"));
						$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
					}
				}

				/////////////////////////////// DELETE ///////////////////////////////

				if(strtolower($args[0]) == "del") {
					if($this->plugin->isInFaction($playerName) == true) {
						if($this->plugin->isLeader($playerName)) {
							$faction = $this->plugin->getPlayerFaction($playerName);
							$this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
							$this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
							$this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
							$this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
							$sender->sendMessage($this->plugin->formatMessage("Faction successfully disbanded!", true));
							if($this->plugin->prefs->get("FactionNametags")) {
								$this->plugin->updateTag($sender->getName());
							}
						} else {
							$sender->sendMessage($this->plugin->formatMessage("You are not leader!"));
						}
					} else {
						$sender->sendMessage($this->plugin->formatMessage("You are not in a faction!"));
					}
				}

				/////////////////////////////// LEAVE ///////////////////////////////

				if(strtolower($args[0] == "leave")) {
					if($this->plugin->isLeader($playerName) == false) {
						$remove = $sender->getPlayer()->getNameTag();
						$faction = $this->plugin->getPlayerFaction($playerName);
						$name = $sender->getName();
						$this->plugin->db->query("DELETE FROM master WHERE player='$name';");
						$sender->sendMessage($this->plugin->formatMessage("You successfully left $faction", true));
						if($this->plugin->prefs->get("FactionNametags")) {
							$this->plugin->updateTag($sender->getName());
						}
					} else {
						$sender->sendMessage($this->plugin->formatMessage("You must delete or give\nleadership first!"));
					}
				}

				/////////////////////////////// SETHOME ///////////////////////////////

				if(strtolower($args[0] == "sethome")) {
					if(!$this->plugin->isInFaction($playerName)) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
						return true;
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "sethome")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					$factionName = $this->plugin->getPlayerFaction($sender->getName());
					$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z, world) VALUES (:faction, :x, :y, :z, :world);");
					$stmt->bindValue(":faction", $factionName);
					$stmt->bindValue(":x", $sender->getX());
					$stmt->bindValue(":y", $sender->getY());
					$stmt->bindValue(":z", $sender->getZ());
					$stmt->bindValue(":world", $sender->getLevel()->getName());
					$result = $stmt->execute();
					$sender->sendMessage($this->plugin->formatMessage("Home updated!", true));
				}

				/////////////////////////////// UNSETHOME ///////////////////////////////

				if(strtolower($args[0] == "unsethome")) {
					if(!$this->plugin->isInFaction($playerName)) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
						return true;
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "unsethome")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					$faction = $this->plugin->getPlayerFaction($sender->getName());
					$this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
					$sender->sendMessage($this->plugin->formatMessage("Home unset!", true));
				}

				/////////////////////////////// HOME ///////////////////////////////

				if(strtolower($args[0] == "home")) {
					if(!$this->plugin->isInFaction($playerName)) {
						$sender->sendMessage($this->plugin->formatMessage("You must be in a faction to do this."));
					}
					if(!$this->plugin->isLeader($playerName) && !$this->plugin->hasPermission($playerName, "home")) {
						$sender->sendMessage($this->plugin->formatMessage("You do not have permission to do this"));
						return true;
					}
					$faction = $this->plugin->getPlayerFaction($sender->getName());
					$result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
					$array = $result->fetchArray(SQLITE3_ASSOC);
					if(!empty($array)) {
						$world = $this->plugin->getServer()->getLevelByName($array['world']);
						$sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $world));
						$sender->sendMessage($this->plugin->formatMessage("Teleported home.", true));
						return true;
					} else {
						$sender->sendMessage($this->plugin->formatMessage("Home is not set."));
					}
				}

				/////////////////////////////// ABOUT ///////////////////////////////

				if(strtolower($args[0] == 'about')) {
					$sender->sendMessage(TextFormat::BLUE . "FactionsPro v1.4.5 by " . TextFormat::BOLD . "Tethered_\n" . TextFormat::RESET . TextFormat::BLUE . "Twitter: " . TextFormat::ITALIC . "@Tethered_");
				}

				/////////////////////////////// INFO ///////////////////////////////

				if(strtolower($args[0]) == 'info') {
					if(isset($args[1])) {
						if( !($this->alphanum($args[1])) or !($this->plugin->factionExists($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("Faction $args[1] does not exist"));
							return true;
						}
						$faction = strtolower($args[1]);
						$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						$message = $array["message"];
						$leader = $this->plugin->getLeader($faction);
						$numPlayers = $this->plugin->getNumberOfPlayers($faction);
						$sender->sendMessage(TextFormat::BOLD . "-------------------------");
						$sender->sendMessage("$faction");
						$sender->sendMessage(TextFormat::BOLD . "Leader: " . TextFormat::RESET . "$leader");
						$sender->sendMessage(TextFormat::BOLD . "# of Players: " . TextFormat::RESET . "$numPlayers");
						$sender->sendMessage(TextFormat::BOLD . "MOTD: " . TextFormat::RESET . "$message");
						$sender->sendMessage(TextFormat::BOLD . "-------------------------");
					} else {
						if(!$this->plugin->isInFaction($playerName)) {
							$sender->sendMessage($this->plugin->formatMessage("You are not in a faction. Use /f info <facname>"));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction(strtolower($sender->getName()));
						$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						$message = $array["message"];
						$leader = $this->plugin->getLeader($faction);
						$numPlayers = $this->plugin->getNumberOfPlayers($faction);
						$sender->sendMessage(TextFormat::BOLD . "-------------------------");
						$sender->sendMessage("$faction");
						$sender->sendMessage(TextFormat::BOLD . "Leader: " . TextFormat::RESET . "$leader");
						$sender->sendMessage(TextFormat::BOLD . "# of Players: " . TextFormat::RESET . "$numPlayers");
						$sender->sendMessage(TextFormat::BOLD . "MOTD: " . TextFormat::RESET . "$message");
						$sender->sendMessage(TextFormat::BOLD . "-------------------------");
					}
				}

				if(strtolower($args[0]) == "help") {
					if(!isset($args[1]) || $args[1] == 1) {
						$sender->sendMessage(TextFormat::BLUE . "FactionsPro Help Page 1 of 3" . TextFormat::RED . "\n/f about\n/f accept\n/f claim\n/f create <name>\n/f del\n/f demote <player>\n/f deny");
						return true;
					}
					if($args[1] == 2) {
						$sender->sendMessage(TextFormat::BLUE . "FactionsPro Help Page 2 of 3" . TextFormat::RED . "\n/f home\n/f help <page>\n/f info\n/f info <faction>\n/f invite <player>\n/f kick <player>\n/f leader <player>\n/f leave");
						return true;
					} else {
						$sender->sendMessage(TextFormat::BLUE . "FactionsPro Help Page 3 of 3" . TextFormat::RED . "\n/f motd\n/f promote <player>\n/f sethome\n/f unclaim\n/f unsethome\n/f unclaim <faction>\n/f forceunclaim [OP]<faction> : Deletes all faction land\n/f forcedelete <faction> : Delete the faction [OP]");
						return true;
					}
				}
			}
		} else {
			$this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Please run command in game"));
		}
		return true;
	}

	public function alphanum($string){
		if(function_exists('ctype_alnum')){
			$return = ctype_alnum($string);
		}else{
			$return = preg_match('/^[a-z0-9]+$/i', $string) > 0;
		}
		return $return;
	}
}
