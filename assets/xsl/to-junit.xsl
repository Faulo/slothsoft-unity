<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
	xmlns:html="http://www.w3.org/1999/xhtml"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:func="http://exslt.org/functions" xmlns:php="http://php.net/xsl"
	extension-element-prefixes="func php">

	<func:function name="php:format-date">
		<xsl:param name="date" />

		<func:result
			select="php:function('Slothsoft\Unity\JUnit::formatDate', string($date))" />
	</func:function>

	<xsl:template match="result">
		<testsuites>
			<xsl:for-each select="process">
				<xsl:variable name="errors"
					select="count(self::*[@result != 0])" />
				<testsuite id="{position() - 1}" package=""
					name="{@package}" hostname="localhost" tests="1" failures="0"
					skipped="0" errors="{$errors}" time="{@duration}"
					timestamp="{php:format-date(@start-time)}">
					<properties />
					<testcase classname="{@package}" name="{@name}"
						time="{@duration}">
						<xsl:if test="$errors != 0">
							<error>
								<xsl:value-of select="@message" />
							</error>
						</xsl:if>
					</testcase>
					<system-out>
						<xsl:value-of select="@stdout" />
					</system-out>
					<system-err>
						<xsl:value-of select="@stderr" />
					</system-err>
				</testsuite>
			</xsl:for-each>

		</testsuites>
	</xsl:template>

	<xsl:template match="test-run">
		<testsuites>
			<xsl:apply-templates
				select=".//test-suite[test-case]" />
		</testsuites>
	</xsl:template>

	<xsl:template match="test-suite">
		<testsuite package="" id="{count(preceding::test-run)}"
			name="{@classname}" hostname="localhost" tests="{@testcasecount}"
			failures="{@failed}" skipped="{@skipped}" errors="{@inconclusive}"
			time="{@duration}" timestamp="{php:format-date(@start-time)}">
			<properties>
				<xsl:copy-of select="properties/*" />
			</properties>
			<xsl:apply-templates select="test-case" />
			<system-out />
			<system-err />
		</testsuite>
	</xsl:template>

	<xsl:template match="test-case">
		<testcase classname="{@classname}" name="{@name}"
			time="{@duration}">
		</testcase>
	</xsl:template>
</xsl:stylesheet>