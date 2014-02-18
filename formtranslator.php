<?php

$infile = "inputform.html";
$instr = file_get_contents($infile);
$pos["headstart"] = stripos($instr,"<head>");
$pos["headend"] = stripos($instr,"</head>");
$pos["bodystart"] = stripos($instr,"<body>");
$pos["bodyend"] = stripos($instr,"</body>")+7;

$input["top"] = substr($instr,0,$pos["headstart"]);
$input["head"] = substr($instr,$pos["headstart"],$pos["headend"]-$pos["headstart"]);
$input["middle"] = substr($instr,$pos["headend"],$pos["bodystart"]-$pos["headend"]);
$input["body"] = substr($instr,$pos["bodystart"],$pos["bodyend"]-$pos["bodystart"]);
$input["bottom"] = substr($instr,$pos["bodyend"]);

$fixatt = array("multiple","checked","disabled");
foreach ($fixatt as $a)
	$input["body"] = str_replace(" $a "," $a=\"\"",$input["body"]);

//for easy debugging
//echo "<pre>";
//var_dump(htmlentities($input["body"]));
//echo "</pre>";

define(NSPACES_ON,true);
$f = (NSPACES_ON) ? "f:" : "";

$parser = xml_parser_create();
xml_set_element_handler($parser, "tagOpen", "tagClosed");
xml_set_character_data_handler($parser, "tagContent");
$curtags = array();

/*START MAIN PARSER LOGIC*/

function tagOpen($parser, $name, $attrs) 
{
	global $outbody, $curtags, $sctag;
	global $outhead, $curformid, $f;

	$sctag = true;
	array_unshift($curtags,$name);
	switch ($curtags[0]) {
		case "FORM":
			if (!isset($attrs["ENCTYPE"]))
			{
				if ($attrs["METHOD"] != "post")
					$method = $attrs["METHOD"];
			}
			else if ($attrs["ENCTYPE"] == "application/x-www-form-urlencoded")
				$method = "urlencoded-post";
			else if ($attrs["ENCTYPE"] == "multipart/form-data")
				$method = "form-data-post";

			$curformid = $attrs["NAME"];

			$outhead .= "<$f"."submission action='".$attrs["ACTION"] . 
				"' method='" . $method . 
				"' id='" . $attrs["NAME"] . "'/>";
			$outbody .= "<div class='form'>";
		break;
		case "INPUT":
			$sctag = false;
			switch ($attrs["TYPE"]) {
				case "text":
					$outbody .= "<$f"."input ref='".$attrs["NAME"] . "'><$f" .
					"label>".$attrs["NAME"]."</$f"."label>"."</$f"."input>";
				break;
				case "submit":
					$outbody .= "<$f"."submit submission='$curformid'><$f" .
					"label>".$attrs["VALUE"]."</$f"."label>"."</$f"."submit>";
				break;
			}
		break;
		default:
			$outbody .= "<".$name;
			foreach ($attrs as $k=>$v)
				$outbody .= " $k=\"$v\"";
			$outbody .= ">";
		break;
	}
}

function tagClosed($parser, $name) 
{
	global $outbody, $curtags, $sctag;
	global $outhead, $curformid, $f;

	switch ($name) {
		case "INPUT":
			//do nothing
		break;
		case "FORM":
			$curformid = "";
			$outbody .= "</div>";
		break;
		default:
			if ($sctag) //self-closing tag
				$outbody = substr($outbody,0,-1) . "/>";
			else
				$outbody .= "</$name>";
		break; }

	array_shift($curtags);
}

function tagContent($parser, $data) 
{
	global $outbody, $curtags, $sctag;
	global $outhead, $curformid, $f;

	switch ($curtags[0]) {
		default:
			$sctag = false;
			$outbody .= $data;
		break; }
}

/*END MAIN PARSER LOGIC*/

if (!xml_parse($parser, $input["body"], true))
{
	$error = xml_error_string(xml_get_error_code($parser));
	$line = xml_get_current_line_number($parser);
	die("HTML error: " . $error . " , line " . $line);
}
xml_parser_free($parser);

$outhead = "<$f"."model>".$outhead."</$f"."model>";
$finaloutput = $input["top"].$input["head"].$outhead.$input["middle"].$outbody.$input["bottom"];
if (NSPACES_ON)
	$finaloutput = str_replace("<html","<html xmlns:f='http://www.w3.org/2002/xforms'",$finaloutput);

$outfile = "output.html";
$fh = fopen($outfile,"w");
if (!fwrite($fh,$finaloutput))
	die("Failed to write to file.");

//for easy debugging
//echo "<pre>OUTPUT: \r\n\r\n" . htmlentities($finaloutput);
//echo "</pre>";

?>