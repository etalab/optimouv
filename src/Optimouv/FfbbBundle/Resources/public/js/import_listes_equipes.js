// Fonction to controller si l'element exists ou pas
jQuery.fn.exists = function(){return this.length>0;}


function addEventHandlerImportListeEquipes(){
  // bouton pour importer une liste de participants
  $("#btn_import_liste_equipes").click(function(){
    console.log("upload listeEquipes");

    var data = new FormData();
    $.each($('#input_liste_equipes')[0].files, function(i, file) {
      data.append('file-'+i, file);
    });

    $.ajax({
      url: '/admin/poules/creer-liste-equipes',
      type: 'POST',
      data: data,
      contentType: false,
      dataType : "json",
      processData: false,
      success: function(data, textStatus, jqXHR)
      {
        if(data.success)
        {
          // Success so call function to process the form
          console.log('SUCCESS: ' + data.msg);

          var nouvelElementId = data.data[0].id;
          console.log('nouvelElementId: ' + nouvelElementId);

          var nouvelElementNom = data.data[0].nom;
          console.log('nouvelElementNom: ' + nouvelElementNom);

          console.log('dateCreation: ' + data.dateCreation);

          // construire une date avec le bon format
          var nouvelElementDateCreation = data.dateCreation;
          var nouvelElementJour = nouvelElementDateCreation.split("-")[2];
          var nouvelElementMois = nouvelElementDateCreation.split("-")[1];
          var nouvelElementAnnee = nouvelElementDateCreation.split("-")[0];

          nouvelElementDateCreation = nouvelElementJour + "/" + nouvelElementMois + "/" + nouvelElementAnnee;
          console.log('nouvelElementDateCreation: ' + nouvelElementDateCreation);

          // mettre à jour les boutons
          //var nouvelleStr = '<tr id=' + nouvelElementId +'> <td>' + nouvelElementId + ' </td>';
          var nouvelleStr = '<tr id=' + nouvelElementId + '>' ;
          nouvelleStr = nouvelleStr + '<td> '+ nouvelElementNom  + ' </td>';
          nouvelleStr = nouvelleStr + '<td> '+ nouvelElementDateCreation  + ' </td>';
          nouvelleStr = nouvelleStr + '<td> ';
          nouvelleStr = nouvelleStr + '   <a href="renommer-liste-equipes/'+ nouvelElementId +'" class="btn btn-info-poule"> Renommer</a> | ';
          nouvelleStr = nouvelleStr + '   <button type="submit" class="btn btn-info-poule" onclick="deleteListeParticipants('+ nouvelElementId +')"> Supprimer</button> |';
          nouvelleStr = nouvelleStr + '   <a href="visualiser-liste-equipes/'+ nouvelElementId +'" class="btn btn-info-poule"> Visualiser</a> ';
          nouvelleStr = nouvelleStr + '</td> </tr> ';
          console.log('nouvelleStr: ' + nouvelleStr);

          // ajouter un nouvel element dans la liste de participants
          $("#listeEquipes").prepend(nouvelleStr);

          // supprimer les enfants de l'élement select liste_partcipants
          //$("#liste_partcipants").empty();
          // rafraiher la liste de participants
          //$.each(data.data, function(index, value){
          //  $("#liste_partcipants").append("<option value=" + value.id + ">" + value.nom + "</option>");
          //});

          // mettre à jour le statut d'upload
          $("#msg_upload_liste_participants").text(data.msg);

          // afficher le msg d'upload
          $("#collapse_statut_upload_participants").removeClass("collapse");

        }
        else
        {

          // nettoyer ancien texte
          $("#msg_upload_liste_participants").empty();

          //console.log("data.msg: " + data.msg);
          console.log("data.msg.length: " + data.msg.length);

          for(j=0; j<data.msg.length; j++){
            // Handle errors here
            var erreurMsg = data.msg[j];
            console.log('erreurMsg : ' + erreurMsg );

            // mettre à jour le statut d'upload
            var erreurMsgSplit = erreurMsg.split('!');
            console.log('erreurMsgSplit : ' + erreurMsgSplit );

            // mettre à jour le contenu du message d'erreur
            for (i = 0 ; i < erreurMsgSplit.length ; i ++ ){
              var iterMsg = erreurMsgSplit[i] + '<br>';
              $("#msg_upload_liste_participants").append(iterMsg);
            }

            // ajouter une escape pour chaque erreur
            $("#msg_upload_liste_participants").append('<br>');


          }

          // afficher le msg d'upload
          $("#collapse_statut_upload_participants").removeClass("collapse");
        }
      },
      error: function(jqXHR, textStatus, errorThrown)
      {
        // Handle errors here
        console.log('ERRORS: ' + textStatus);
      }
    });

  });

}

function hideStatutChargement(){
  $("#statut_chargement_participants").hide();
}


$(document).ready(function () {

  addEventHandlerImportListeEquipes();
  hideStatutChargement();

});

