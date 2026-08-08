def runTests(def name = "tests") {
    catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
        callShell 'composer update --prefer-lowest'

        dir('.reports') {
            deleteDir()
        }

        def report = ".reports/${name}.xml"

        catchError(stageResult: 'UNSTABLE', buildResult: 'UNSTABLE', catchInterruptions: false) {
            callShell "composer exec phpunit -- --log-junit ${report}"
        }

        if (fileExists(report)) {
            junit report
        }
    }
}

pipeline {
    agent none
    options {
        disableConcurrentBuilds()
        disableResume()
        disableRestartFromStage()
    }
    environment {
        COMPOSER_PROCESS_TIMEOUT = '3600'
        EMAIL_TEST_TIME = '1745158411'
        EMAIL_TEST_CODE = '177824'
        UNITY_LOGGING = ''
    }
    stages {
        stage('Linux') {
            agent {
                label 'compose-unity && linux'
            }
            steps {
                script {
                    withUnity {
                        runTests()
                    }
                }
            }
        }
        stage('Windows') {
            agent {
                label 'compose-unity && windows'
            }
            steps {
                script {
                    withUnity {
                        runTests()
                    }
                }
            }
        }
    }
}