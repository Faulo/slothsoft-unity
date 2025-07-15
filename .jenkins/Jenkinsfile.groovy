pipeline {
	agent none
	options {
		disableConcurrentBuilds()
		disableResume()
	}
	stages {
		stage('Linux') {
			agent {
				label 'docker && linux'
			}
			steps {
				script {
					def versions = ["7.4", "8.0", "8.1", "8.2", "8.3"]

					for (version in versions) {
						def image = "faulo/farah:${version}"

						stage("PHP: ${version}") {
							callShell "docker pull ${image}"

							docker.image(image).inside {
								callShell 'composer update --prefer-lowest'

								dir('.reports') {
									deleteDir()
								}

								def report = ".reports/${version}.xml"

								catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE') {
									callShell "composer exec phpunit -- --log-junit ${report}"
								}

								if (fileExists(report)) {
									junit report
								}
							}
						}
					}
				}
			}
		}
		stage('Windows') {
			agent {
				label 'docker && windows'
			}
			steps {
				script {
					def versions = ["7.4", "8.0", "8.1", "8.2", "8.3"]

					for (version in versions) {
						def image = "faulo/farah:${version}"

						stage("PHP: ${version}") {
							callShell "docker pull ${image}"

							docker.image(image).inside {
								callShell 'composer update --prefer-lowest'

								dir('.reports') {
									deleteDir()
								}

								def report = ".reports/${version}.xml"

								catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE') {
									callShell "composer exec phpunit -- --log-junit ${report}"
								}

								if (fileExists(report)) {
									junit report
								}
							}
						}
					}
				}
			}
		}
	}
}