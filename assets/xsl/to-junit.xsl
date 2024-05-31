<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:func="http://exslt.org/functions" xmlns:php="http://php.net/xsl"
	extension-element-prefixes="func php">

	<func:function name="php:format-date">
		<xsl:param name="date" />

		<func:result select="php:function('Slothsoft\Unity\JUnit::formatDate', string($date))" />
	</func:function>


	<!-- Slothsoft process XML -->
	<xsl:template match="result">
		<testsuites>
			<xsl:for-each select="process">
				<testsuite id="{position() - 1}" package="" name="{@package}" hostname="localhost" tests="1" failures="{count(failure)}" skipped="{count(skipped)}" errors="{count(error)}"
					time="{@duration}" timestamp="{php:format-date(@start-time)}">
					<properties />
					<testcase classname="{@package}" name="{@name}" time="{@duration}">
						<xsl:copy-of select="skipped" />
						<xsl:copy-of select="failure" />
						<xsl:copy-of select="error" />
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


	<!-- Unity Test Runner XML -->
	<xsl:template match="test-run">
		<testsuites>
			<xsl:apply-templates select=".//test-suite[test-case]" />
		</testsuites>
	</xsl:template>

	<xsl:template match="test-suite">
		<testsuite package="" id="{count(preceding::test-run)}" name="{@classname}" hostname="localhost" tests="{@testcasecount}" failures="{@failed}" skipped="{@skipped}" errors="{@inconclusive}"
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
		<testcase classname="{@classname}" name="{@name}" time="{@duration}">
			<xsl:choose>
				<xsl:when test="@label and failure">
					<error type="{@label}" message="{failure/message}">
						<xsl:value-of select="failure/stack-trace" />
					</error>
				</xsl:when>
				<xsl:when test="failure">
					<failure type="Assert" message="{failure/message}">
						<xsl:value-of select="failure/stack-trace" />
					</failure>
				</xsl:when>
			</xsl:choose>
		</testcase>
	</xsl:template>


	<!-- dotnet format XML -->
	<xsl:template match="Reports">
		<testsuites>
			<testsuite package="" id="0" name="ContinuousIntegration.DotNet.Format" hostname="localhost" tests="{count(Report)}" failures="{count(Report)}" skipped="0" errors="0" time="0"
				timestamp="{php:format-date(@Time)}">
				<properties />
				<xsl:apply-templates select="Report" />
				<system-out />
				<system-err />
			</testsuite>
		</testsuites>
	</xsl:template>

	<xsl:template match="Report">
		<testcase classname="VerifyNoChanges" name="{@FileName}" time="0">
			<failure type="FormattingError" message="{@FormatDescription}">
				<xsl:attribute name="message">
                    <xsl:for-each select="FileChange">
                        <xsl:sort select="@LineNumber" data-type="number" />
                        <xsl:sort select="@CharNumber" data-type="number" />
		                <xsl:text>line </xsl:text>
                        <xsl:value-of select="substring('    ', 1, 4 - string-length(@LineNumber))" />
                        <xsl:value-of select="@LineNumber" />
		                <xsl:text>: </xsl:text>
		                <xsl:value-of select="@FormatDescription" />
		                <xsl:text>
</xsl:text>
                    </xsl:for-each>
                </xsl:attribute>
				<xsl:text>in </xsl:text>
				<xsl:value-of select="@FilePath" />
			</failure>
		</testcase>
	</xsl:template>

	<xsl:template match="FileChange">
		<testcase classname="{../@FileName}" name="VerifyNoChanges(&quot;{../@FileName}:{@LineNumber}&quot;)" time="0">
			<failure type="{@DiagnosticId}" message="{@FormatDescription}">
				<xsl:text>in </xsl:text>
				<xsl:value-of select="../@FilePath" />
				<xsl:text>:</xsl:text>
				<xsl:value-of select="@LineNumber" />
			</failure>
		</testcase>
	</xsl:template>
</xsl:stylesheet>