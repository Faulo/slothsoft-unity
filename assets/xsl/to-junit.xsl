<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
	xmlns:html="http://www.w3.org/1999/xhtml"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:template match="error">
       <testsuites id="{generate-id()}" name="ERROR"
            tests="1" failures="0" disabled="0"
            errors="1" time="1">
            <testsuite id="{generate-id()}" name="ERROR" hostname="localhost"
                tests="1" failures="0" disabled="0"
                errors="1" time="1">
                <testcase id="{generate-id()}" name="ERROR" time="1">
                    <failure message="{.}"/>
                </testcase>
            </testsuite>
        </testsuites>
    </xsl:template>

    <xsl:template match="success">
       <testsuites id="{generate-id()}" name="SUCCESS"
            tests="1" failures="0" disabled="0"
            errors="0" time="1">
            <testsuite id="{generate-id()}" name="SUCCESS" hostname="localhost"
                tests="1" failures="0" disabled="0"
                errors="0" time="1">
                <testcase id="{generate-id()}" name="SUCCESS" time="1"/>
            </testsuite>
        </testsuites>
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