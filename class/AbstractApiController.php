<?php

abstract class AbstractApiController {

	/**
	 * Get requested action
	 */
	protected function getRequestAction() {

		if( isset($_SERVER['PATH_INFO']) ) {

			$action = trim($_SERVER['PATH_INFO'], '/'); // secure?
			return $action;
		}
	}

	/**
	 * Get requested method
	 */
	protected function getRequestMethod() {
		return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
	}

	/**
	 * Send error message
	 * @param string message
	 * @param int http_code
	 */
	protected function error( $message, $http_code = null ) {

		// TODO use good http code

		$this->outputJSON(array('error' => $message));
	}

	/**
	 * Output results as Json
	 * @param array results
	 * @param bool pretty
	 */
	protected function outputJSON( $results, $pretty = false ) {

		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Content-type: application/json');

		// Petty JSON
		if( $pretty ) {

			echo json_encode($results, JSON_PRETTY_PRINT);
		}
		else {

			echo json_encode($results);				
		}

		exit();
	}

	/**
	 * Create OPML formated file
	 */
	protected function outputOPML( $feeds ) {

		header('Content-type: application/x-xml; charset=UTF-8');
		header('Content-Disposition: attachment; filename="subscriptions.opml"');

		// Code from: https://github.com/pfeff/opml.php
		$xml = new XMLWriter();
		$xml->openURI('php://output');

		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElement('opml');
		$xml->writeAttribute('version', '2.0');

		// Header
		$xml->startElement('head');
		$xml->writeElement('title', 'Shaarli API OPML');
		$xml->writeElement('dateModified', date("D, d M Y H:i:s T"));
		$xml->endElement();

		// Body
		$xml->startElement('body');

			foreach ($feeds as $feed) {

			    $xml->startElement('outline');
			    $xml->writeAttribute('text', $feed['title']);
			    $xml->writeAttribute('htmlUrl', $feed['link']);
			    $xml->writeAttribute('xmlUrl', $feed['url']);
			    $xml->endElement();
			}

		$xml->endElement();

		$xml->endElement();
		$xml->endDocument();

		$xml->flush();
	}

	/**
	 * Output as RSS
	 * @param entries
	 * @param config
	 */
	protected function outputRSS( $entries, $config ) {

		header('Content-type: application/rss+xml; charset=UTF-8');
		
		// Inspired from http://www.phpntips.com/xmlwriter-2009-06/
		$xml = new XMLWriter();

		// Output directly to the user
		$xml->openURI('php://output');
		$xml->startDocument('1.0');
		$xml->setIndent(2);
		//rss
		$xml->startElement('rss');
		$xml->writeAttribute('version', '2.0');
		$xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');

		//channel
		$xml->startElement('channel');

		// title, desc, link, date
		$xml->writeElement('title', $config['title']);
		// $xml->writeElement('description', $config['description']);
		// $xml->writeElement('link', 'http://www.example.com/rss.hml');
		$xml->writeElement('pubDate', date('r'));

		if( !empty($entries) ) {

			foreach( $entries as $entry ) {

				// item
				$xml->startElement('item');
				$xml->writeElement('title', $entry['title']);
				$xml->writeElement('link', $entry['permalink']);
				$xml->startElement('description');
				$xml->writeCData($entry['content']);
				$xml->endElement();
				$xml->writeElement('pubDate', date('r', strtotime($entry['date'])));

				// category
				// $xml->startElement('category');
				// $xml->writeAttribute('domain', 'http://www.example.com/cat1.htm');
				// $xml->text('News');
				// $xml->endElement();

				// end item
				$xml->endElement();
			}	
		}

		// end channel
		$xml->endElement();

		// end rss
		$xml->endElement();

		// end doc
		$xml->endDocument();

		// flush
		$xml->flush();
	}
}
