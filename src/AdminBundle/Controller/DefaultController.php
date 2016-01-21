<?php

namespace AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use AdminBundle\Entity\User;
use AdminBundle\Entity\Picture;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('AdminBundle:Default:index.html.twig');
    }

    public function loginAction()
    {
        $email = htmlspecialchars( trim($_POST["email"]) );
        $pass = md5( htmlspecialchars( trim($_POST["pass"]) ) );
        

        //check User email and password
        $User = $this->getDoctrine()
        ->getRepository('AdminBundle:User')
        ->findOneBy(array('email' => $email, 'pass' => $pass));

		$session = $this->getRequest()->getSession();
        
        if (!$User) {
        	$session->set('is_auth', false);
            return new Response(0);
        }
        else {
            $session->set('is_auth', true);
            $session->set('fio', $User->getFio());
            $session->set('email', $User->getEmail());
            return new Response(1);
        }
    }

    public function cabinetAction()
    {
        $session = $this->getRequest()->getSession();

        if ($session->get('is_auth')=="") { $session->set('is_auth', false); }
        
        if ($session->get('is_auth') == true) {
            $User = $this->getDoctrine()
                                        ->getRepository('AdminBundle:User')
                                        ->findOneBy(array('email' => $session->get('email')));

            return $this->render('AdminBundle:Default:cabinet.html.twig',
                                    array('fio' => $User->getFio(),
                                          'email' => $User->getEmail(),
                                          'picture' => $User->getPicture(),
                                          'file_err' => '',
                                        )
                                );
        }
        else {
            return $this->render('AdminBundle:Default:index.html.twig');              
        }
        
    }

    public function logoutAction()
    {
        $session = $this->getRequest()->getSession();
        $session->set('is_auth', false);
        $session->set('fio', '');
        $session->set('email', '');

        return $this->render('AdminBundle:Default:index.html.twig');  

    }

    public function loadfileAction()
    {
        $session = $this->getRequest()->getSession();

        if ($_FILES && $_FILES['filename']['error']== UPLOAD_ERR_OK) {

                //check upload file parameters
                $file_err = '';
                $type = $_FILES['filename']['type'];
                $arrno = array("image/jpg","image/jpeg","image/png");
                if(!in_array($type,$arrno)) {
                    $file_err = "Не верный тип файла; ";
                }

                if ($_FILES['filename']['size'] > 1024000) {
                    $file_err = $file_err."Большой размер файла;";
                }

                $Picture = $this->getDoctrine()
                                            ->getRepository('AdminBundle:Picture')
                                            ->findOneBy(array('filename' => $_FILES['filename']['name']));
                if ($Picture) {
                    $file_err = $file_err."Файл с таким названием уже существует;";
                }
                
                $User = $this->getDoctrine()
                                            ->getRepository('AdminBundle:User')
                                            ->findOneBy(array('email' => $session->get('email')));

                //if all file parameters are correct
                if ($file_err == '') {
                    $name = $_FILES['filename']['name'];
                    $path = 'userfiles/'.$User->getId();

                    //if user's folder is not exist -> create it
                    if (!file_exists($path)) {
                        mkdir($path,0755);;
                    }


                    move_uploaded_file($_FILES['filename']['tmp_name'], $path.'/'.$name);


                    $Picture = new Picture();
                    $Picture->setFilename($name);
                    $Picture->setFilepath($path);
                    $Picture->setUser($User);

                    $em = $this->getDoctrine()->getManager();
                    $em->persist($Picture);
                    $em->flush();
                }
        }

        return $this->redirect($this->generateUrl('user_cabinet', 
                                    array('fio' => $session->get('fio'),
                                          'email' => $session->get('email'),
                                          'picture' => $User->getPicture(),
                                          'file_err' => $file_err,
                                        )
                                ));

    }

    public function delfileAction()
    {
        $session = $this->getRequest()->getSession();

        $request = $this -> getRequest();
        $fileid = $request->query->get('fileid');

        $Picture = $this->getDoctrine()
                                    ->getRepository('AdminBundle:Picture')
                                    ->findOneBy(array('id' => $fileid));
        
        if ($Picture) {
            //delete file from disk
            $fullpath = ''.$Picture->getFilepath().'/'.$Picture->getFilename();
            @chmod($fullpath, 0777);
            @unlink($fullpath);


            //del line from DB
            $em = $this->getDoctrine()->getManager();
            $em->remove($Picture);
            $em->flush();
        }

        $User = $this->getDoctrine()
                                    ->getRepository('AdminBundle:User')
                                    ->findOneBy(array('email' => $session->get('email')));

        return $this->render('AdminBundle:Default:cabinet.html.twig',
                                    array('fio' => $session->get('fio'),
                                          'email' => $session->get('email'),
                                          'picture' => $User->getPicture(),
                                          'file_err' => '',
                                        )
                            );

    }
}
