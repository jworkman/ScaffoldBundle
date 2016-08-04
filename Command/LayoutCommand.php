<?php

namespace JWorkman\ScaffoldBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Symfony\Component\Console\Question\ChoiceQuestion;

class LayoutCommand extends GenerateDoctrineCommand
{

    protected function configure()
    {
        $this
            ->setName('scaffold:layout')
            ->setDescription('Generate a global scaffold layout file.');
    }

    protected function createGenerator()
    {
        return;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->questionHelper                       = $this->getQuestionHelper();
        $kernel         = $this->getContainer()->get('kernel');
        $app_dir        = $kernel->getRootDir();
        $target_path    = $app_dir . '/Resources/views/scaffold.html.twig';
        $tmpl_path      = $kernel->locateResource('@JWorkmanScaffoldBundle/Resources/views/scaffold.html.twig');

        if ( file_exists( $target_path ) ) {
            $question       = new ConfirmationQuestion('<error>Scaffold layout file already exists! Do you want to overwrite it?</error>', false);
            $overwrite      = $this->questionHelper->ask($input, $output, $question);

            if ( !$overwrite ) { return false; }
        }   

        $tmpl = file_get_contents( $tmpl_path );

        if ( $tmpl && file_put_contents($target_path, $tmpl) ) {
            $output->writeln('<info>Template created successfully! Placed template in "' . $tmpl_path . '"</info>');
            return true;
        } else {
            $output->writeln('<error>Error! Could not generate layout file in "' . $tmpl_path . '"</error>');
        }

        return false;

    }

}