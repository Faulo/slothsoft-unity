<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:func="http://exslt.org/functions" xmlns:php="http://php.net/xsl" xmlns:set="http://exslt.org/sets"
	extension-element-prefixes="func php set">

	<func:function name="php:format-date">
		<xsl:param name="date" />

		<func:result select="php:function('Slothsoft\Unity\JUnit::formatDate', string($date))" />
	</func:function>


	<!-- Canonical unity-command report envelope. -->
	<xsl:template match="unity-command-report">
		<xsl:choose>
			<xsl:when test="problem">
				<xsl:call-template name="unity-command-operation">
					<xsl:with-param name="report" select="." />
				</xsl:call-template>
			</xsl:when>
			<xsl:when test="source/testsuites">
				<xsl:copy-of select="source/testsuites" />
			</xsl:when>
			<xsl:when test="source/test-run">
				<xsl:apply-templates select="source/test-run" mode="unity-command" />
			</xsl:when>
			<xsl:when test="source/result[process]">
				<xsl:apply-templates select="source/result" mode="unity-command" />
			</xsl:when>
			<xsl:when test="source/Reports">
				<xsl:apply-templates select="source/Reports" />
			</xsl:when>
			<xsl:otherwise>
				<xsl:call-template name="unity-command-operation">
					<xsl:with-param name="report" select="." />
				</xsl:call-template>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template name="unity-command-operation">
		<xsl:param name="report" />

		<testsuites>
			<testsuite id="0" package="{$report/@package}" name="{$report/@name}" hostname="localhost" tests="1" failures="{count($report/problem[@kind = 'failure'])}"
				errors="{count($report/problem[@kind = 'error'])}" skipped="0" time="{$report/@duration}" timestamp="{$report/@timestamp}">
				<properties>
					<xsl:if test="$report/@exit-code">
						<property name="unity-command.exit-code" value="{$report/@exit-code}" />
					</xsl:if>
					<xsl:if test="$report/source/*">
						<property name="unity-command.source-root" value="{name($report/source/*[1])}" />
					</xsl:if>
					<xsl:for-each select="$report/warning">
						<property name="unity-command.warning.{position()}" value="{.}" />
					</xsl:for-each>
				</properties>
				<testcase classname="{$report/@classname}" name="{$report/@name}" time="{$report/@duration}">
					<xsl:choose>
						<xsl:when test="$report/problem[@kind = 'failure']">
							<failure type="{$report/problem/@type}" message="{$report/problem/@message}">
								<xsl:value-of select="$report/problem" />
							</failure>
						</xsl:when>
						<xsl:when test="$report/problem[@kind = 'error']">
							<error type="{$report/problem/@type}" message="{$report/problem/@message}">
								<xsl:value-of select="$report/problem" />
							</error>
						</xsl:when>
					</xsl:choose>
				</testcase>
				<system-out>
					<xsl:value-of select="$report/standard-output" />
				</system-out>
				<system-err>
					<xsl:value-of select="$report/standard-error" />
					<xsl:if test="string-length($report/standard-error) &gt; 0 and $report/warning and substring($report/standard-error, string-length($report/standard-error), 1) != '&#10;' and substring($report/standard-error, string-length($report/standard-error), 1) != '&#13;'">
						<xsl:text>&#10;</xsl:text>
					</xsl:if>
					<xsl:for-each select="$report/warning">
						<xsl:text>WARNING: </xsl:text>
						<xsl:value-of select="." />
						<xsl:if test="position() != last()">
							<xsl:text>&#10;</xsl:text>
						</xsl:if>
					</xsl:for-each>
				</system-err>
			</testsuite>
		</testsuites>
	</xsl:template>


	<!-- Direct transformations retain the legacy 2.20 result shape. -->
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

	<!-- Richer process mapping is available only through the unity-command envelope. -->
	<xsl:template match="result" mode="unity-command">
		<testsuites>
			<xsl:apply-templates select="process" mode="unity-command-process" />
		</testsuites>
	</xsl:template>

	<xsl:template match="process" mode="unity-command-process">
		<testsuite id="{position() - 1}" package="{@package}" hostname="localhost" tests="1" failures="{count(failure[not(../error)])}" skipped="{count(skipped[not(../error or ../failure)])}"
			errors="{count(error)}">
			<xsl:attribute name="name">
				<xsl:choose>
					<xsl:when test="string-length(@package) &gt; 0"><xsl:value-of select="@package" /></xsl:when>
					<xsl:otherwise>unity-command</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<xsl:attribute name="time">
				<xsl:choose>
					<xsl:when test="string-length(@duration) &gt; 0"><xsl:value-of select="@duration" /></xsl:when>
					<xsl:otherwise>0</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<xsl:attribute name="timestamp"><xsl:value-of select="php:format-date(@start-time)" /></xsl:attribute>
			<properties>
				<xsl:if test="@result">
					<property name="unity-process.exit-code" value="{@result}" />
				</xsl:if>
				<xsl:for-each select="ancestor::unity-command-report/warning">
					<property name="unity-command.warning.{position()}" value="{.}" />
				</xsl:for-each>
			</properties>
			<testcase>
				<xsl:attribute name="classname">
					<xsl:choose>
						<xsl:when test="string-length(@package) &gt; 0"><xsl:value-of select="@package" /></xsl:when>
						<xsl:otherwise>unity-command</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:attribute name="name">
					<xsl:choose>
						<xsl:when test="string-length(@name) &gt; 0"><xsl:value-of select="@name" /></xsl:when>
						<xsl:otherwise>Unity process</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:attribute name="time">
					<xsl:choose>
						<xsl:when test="string-length(@duration) &gt; 0"><xsl:value-of select="@duration" /></xsl:when>
						<xsl:otherwise>0</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:choose>
					<xsl:when test="error">
						<error>
							<xsl:attribute name="type">
								<xsl:choose>
									<xsl:when test="string-length(error/@type) &gt; 0"><xsl:value-of select="error/@type" /></xsl:when>
									<xsl:otherwise>UnityProcessError</xsl:otherwise>
								</xsl:choose>
							</xsl:attribute>
							<xsl:attribute name="message"><xsl:value-of select="error/@message" /></xsl:attribute>
							<xsl:value-of select="error" />
						</error>
					</xsl:when>
					<xsl:when test="failure">
						<failure>
							<xsl:attribute name="type">
								<xsl:choose>
									<xsl:when test="string-length(failure/@type) &gt; 0"><xsl:value-of select="failure/@type" /></xsl:when>
									<xsl:otherwise>UnityProcessFailure</xsl:otherwise>
								</xsl:choose>
							</xsl:attribute>
							<xsl:attribute name="message"><xsl:value-of select="failure/@message" /></xsl:attribute>
							<xsl:value-of select="failure" />
						</failure>
					</xsl:when>
					<xsl:when test="skipped">
						<skipped message="{skipped/@message}"><xsl:value-of select="skipped" /></skipped>
					</xsl:when>
				</xsl:choose>
			</testcase>
			<system-out><xsl:value-of select="@stdout" /></system-out>
			<system-err>
				<xsl:value-of select="@stderr" />
				<xsl:if test="string-length(@stderr) &gt; 0 and ancestor::unity-command-report/warning"><xsl:text>&#10;</xsl:text></xsl:if>
				<xsl:for-each select="ancestor::unity-command-report/warning">
					<xsl:text>WARNING: </xsl:text><xsl:value-of select="." />
					<xsl:if test="position() != last()"><xsl:text>&#10;</xsl:text></xsl:if>
				</xsl:for-each>
			</system-err>
		</testsuite>
	</xsl:template>


	<!-- Direct transformations retain the legacy 2.20 Unity Test Runner shape. -->
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

	<!-- unity-command flattens suites while preserving every Unity case once. -->
	<xsl:template match="test-run" mode="unity-command">
		<xsl:variable name="reported-problems"
			select=".//test-case[@result = 'Inconclusive' or @label = 'Error' or @result = 'Failed' or (failure and not(@result = 'Skipped') and not(@result = 'Ignored') and not(@runstate = 'Skipped') and not(@runstate = 'Ignored'))]" />
		<testsuites>
			<xsl:apply-templates select=".//test-suite[test-case]" mode="unity-test-suite" />
			<xsl:if test="@unity-exit-code and number(@unity-exit-code) != 0 and not($reported-problems)">
				<xsl:call-template name="unity-command-test-process-error" />
			</xsl:if>
		</testsuites>
	</xsl:template>

	<xsl:template name="unity-command-test-process-error">
		<testsuite package="{ancestor::unity-command-report/@package}" id="{count(.//test-suite[test-case])}" name="Unity Test Runner process" hostname="localhost" tests="1" failures="0" skipped="0" errors="1"
			time="{ancestor::unity-command-report/@duration}" timestamp="{ancestor::unity-command-report/@timestamp}">
			<properties>
				<property name="unity-process.exit-code" value="{@unity-exit-code}" />
			</properties>
			<testcase classname="unity-command.tests" name="Unity Test Runner process" time="{ancestor::unity-command-report/@duration}">
				<error type="UnityProcessError" message="Unity test process exited with code {@unity-exit-code}.">Unity Test Runner returned a non-zero process exit code.</error>
			</testcase>
			<system-out><xsl:value-of select="ancestor::unity-command-report/standard-output" /></system-out>
			<system-err><xsl:value-of select="ancestor::unity-command-report/standard-error" /></system-err>
		</testsuite>
	</xsl:template>

	<xsl:template match="test-suite" mode="unity-test-suite">
		<xsl:variable name="skipped-cases" select="test-case[@result = 'Skipped' or @result = 'Ignored' or @runstate = 'Skipped' or @runstate = 'Ignored']" />
		<xsl:variable name="error-cases"
			select="test-case[@result = 'Inconclusive' or @label = 'Error' or (@result = 'Failed' and string-length(@label) &gt; 0 and not(@label = 'Failed') and not(@label = 'Failure') and not(@label = 'Assertion'))]" />
		<xsl:variable name="failure-cases"
			select="test-case[(@result = 'Failed' or failure) and not(@result = 'Skipped') and not(@result = 'Ignored') and not(@result = 'Inconclusive') and not(@label = 'Error') and not(@result = 'Failed' and string-length(@label) &gt; 0 and not(@label = 'Failed') and not(@label = 'Failure') and not(@label = 'Assertion'))]" />

		<testsuite package="" id="{count(preceding::test-suite[test-case]) + count(ancestor::test-suite[test-case])}" hostname="localhost" tests="{count(test-case)}" failures="{count($failure-cases)}"
			skipped="{count($skipped-cases)}" errors="{count($error-cases)}">
			<xsl:attribute name="name">
				<xsl:choose>
					<xsl:when test="string-length(@classname) &gt; 0"><xsl:value-of select="@classname" /></xsl:when>
					<xsl:when test="string-length(@fullname) &gt; 0"><xsl:value-of select="@fullname" /></xsl:when>
					<xsl:when test="string-length(@name) &gt; 0"><xsl:value-of select="@name" /></xsl:when>
					<xsl:otherwise>Unity Test Runner</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<xsl:attribute name="time">
				<xsl:choose>
					<xsl:when test="string-length(@duration) &gt; 0"><xsl:value-of select="@duration" /></xsl:when>
					<xsl:otherwise>0</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<xsl:attribute name="timestamp">
				<xsl:choose>
					<xsl:when test="string-length(@start-time) &gt; 0"><xsl:value-of select="php:format-date(@start-time)" /></xsl:when>
					<xsl:otherwise><xsl:value-of select="php:format-date(ancestor::test-run/@start-time)" /></xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<properties>
				<xsl:for-each select="properties/property[string-length(@name) &gt; 0]">
					<property name="{@name}">
						<xsl:attribute name="value">
							<xsl:choose>
								<xsl:when test="@value"><xsl:value-of select="@value" /></xsl:when>
								<xsl:otherwise><xsl:value-of select="." /></xsl:otherwise>
							</xsl:choose>
						</xsl:attribute>
					</property>
				</xsl:for-each>
				<xsl:for-each select="ancestor::unity-command-report/warning">
					<property name="unity-command.warning.{position()}" value="{.}" />
				</xsl:for-each>
			</properties>
			<xsl:apply-templates select="test-case" mode="unity-test-case" />
			<system-out>
				<xsl:for-each select="test-case/output">
					<xsl:value-of select="." />
					<xsl:if test="position() != last()"><xsl:text>&#10;</xsl:text></xsl:if>
				</xsl:for-each>
			</system-out>
			<system-err>
				<xsl:for-each select="ancestor::unity-command-report/warning">
					<xsl:text>WARNING: </xsl:text><xsl:value-of select="." />
					<xsl:if test="position() != last()"><xsl:text>&#10;</xsl:text></xsl:if>
				</xsl:for-each>
			</system-err>
		</testsuite>
	</xsl:template>

	<xsl:template match="test-case" mode="unity-test-case">
		<testcase>
			<xsl:attribute name="classname">
				<xsl:choose>
					<xsl:when test="string-length(@classname) &gt; 0"><xsl:value-of select="@classname" /></xsl:when>
					<xsl:when test="string-length(../@classname) &gt; 0"><xsl:value-of select="../@classname" /></xsl:when>
					<xsl:when test="string-length(../@fullname) &gt; 0"><xsl:value-of select="../@fullname" /></xsl:when>
					<xsl:otherwise>Unity.TestRunner</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<xsl:attribute name="name">
				<xsl:choose>
					<xsl:when test="string-length(@name) &gt; 0"><xsl:value-of select="@name" /></xsl:when>
					<xsl:when test="string-length(@fullname) &gt; 0"><xsl:value-of select="@fullname" /></xsl:when>
					<xsl:otherwise>Unity test case</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<xsl:attribute name="time">
				<xsl:choose>
					<xsl:when test="string-length(@duration) &gt; 0"><xsl:value-of select="@duration" /></xsl:when>
					<xsl:otherwise>0</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>
			<xsl:choose>
				<xsl:when test="@result = 'Skipped' or @result = 'Ignored' or @runstate = 'Skipped' or @runstate = 'Ignored'">
					<skipped>
						<xsl:attribute name="message">
							<xsl:choose>
								<xsl:when test="reason/message"><xsl:value-of select="reason/message" /></xsl:when>
								<xsl:when test="failure/message"><xsl:value-of select="failure/message" /></xsl:when>
								<xsl:otherwise><xsl:value-of select="@label" /></xsl:otherwise>
							</xsl:choose>
						</xsl:attribute>
						<xsl:value-of select="reason/stack-trace | failure/stack-trace" />
					</skipped>
				</xsl:when>
				<xsl:when test="@result = 'Inconclusive' or @label = 'Error' or (@result = 'Failed' and string-length(@label) &gt; 0 and not(@label = 'Failed') and not(@label = 'Failure') and not(@label = 'Assertion'))">
					<error>
						<xsl:attribute name="type">
							<xsl:choose>
								<xsl:when test="string-length(@label) &gt; 0"><xsl:value-of select="@label" /></xsl:when>
								<xsl:otherwise>Inconclusive</xsl:otherwise>
							</xsl:choose>
						</xsl:attribute>
						<xsl:attribute name="message"><xsl:value-of select="failure/message | reason/message" /></xsl:attribute>
						<xsl:value-of select="failure/stack-trace | reason/stack-trace" />
					</error>
				</xsl:when>
				<xsl:when test="@result = 'Failed' or failure">
					<failure type="Assert" message="{failure/message}"><xsl:value-of select="failure/stack-trace" /></failure>
				</xsl:when>
			</xsl:choose>
		</testcase>
	</xsl:template>

	<!-- dotnet format XML. -->
	<xsl:template match="Reports">
		<xsl:variable name="files" select="set:distinct(.//@FilePath)" />
		<testsuites>
			<testsuite package="" id="0" name="ContinuousIntegration" hostname="localhost" tests="{count($files)}" failures="{count($files)}" skipped="0" errors="0" time="0"
				timestamp="{php:format-date(@Time)}">
				<properties />
				<xsl:for-each select="$files">
					<xsl:call-template name="dotnet-report">
						<xsl:with-param name="reports" select="//Report[@FilePath = current()]" />
					</xsl:call-template>
				</xsl:for-each>
				<system-out />
				<system-err />
			</testsuite>
		</testsuites>
	</xsl:template>

	<xsl:template name="dotnet-report">
		<xsl:param name="reports" />
		<testcase classname="DotNet.Format" name="VerifyNoChanges(&quot;{$reports/@FileName}&quot;)" time="0">
			<failure type="FormattingError">
				<xsl:attribute name="message">
					<xsl:for-each select="$reports/FileChange">
						<xsl:sort select="@LineNumber" data-type="number" />
						<xsl:sort select="@CharNumber" data-type="number" />
						<xsl:text>line </xsl:text>
						<xsl:value-of select="substring('    ', 1, 4 - string-length(@LineNumber))" />
						<xsl:value-of select="@LineNumber" />
						<xsl:text>: </xsl:text>
						<xsl:value-of select="@FormatDescription" />
						<xsl:text>&#10;</xsl:text>
					</xsl:for-each>
				</xsl:attribute>
				<xsl:text>in </xsl:text>
				<xsl:value-of select="$reports/@FilePath" />
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
