<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
	xmlns:html="http://www.w3.org/1999/xhtml"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template match="error">
		<xsl:copy-of select="." />
	</xsl:template>

	<xsl:template match="test-run">
		<testsuites id="{generate-id()}" name="TODO: NAME"
			tests="{@testcasecount}" failures="{@failed}" disabled="{@skipped}"
			errors="{@inconclusive}" time="{sum(test-suite/@duration)}">

			<xsl:apply-templates select="*" />
		</testsuites>
	</xsl:template>

	<xsl:template match="properties">
		<xsl:copy-of select="." />
	</xsl:template>

	<xsl:template match="test-suite">
		<testsuite id="{@id}" name="{@name}" hostname="localhost"
			tests="{@testcasecount}" failures="{@failed}" disabled="{@skipped}"
			errors="{@inconclusive}" time="{@duration}" timestamp="{@start-time}">
			<xsl:apply-templates select="*" />
		</testsuite>
	</xsl:template>

	<xsl:template match="test-case">
		<testcase assertions="{@asserts}" classname="{@classname}"
			status="{@result}" name="{@methodname}" time="{@duration}">
			<xsl:copy-of select="*" />
		</testcase>
	</xsl:template>
</xsl:stylesheet>