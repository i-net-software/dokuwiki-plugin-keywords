<?php
/**
 * Default settings for the chatgpt plugin
 *
 * @author Gerry Weißbach <tools@inetsoftware.de>
 */

$conf['APIModel'] = "gpt-3.5-turbo";
$conf['APIRole'] = "As a keyword generator, your task is to generate a list of FIVE (5) semantically related keywords not directly stated in the provided text. These keywords should be in the same language as the provided text and should reflect the information a user might search for when seeking the content in the text. Your response should consist only of keywords of potential search queries a user of our product might enter into our help system, each separated by a comma ','. Avoid using non-alphanumeric characters. Keep your response concise. ONLY RETURN 5 to 10 KEYWORDS. NO MORE.";
