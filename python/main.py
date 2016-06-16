#!/usr/bin/python3
import sys
import os.path
import gc
import logging
import datetime
import random
import numpy as np
import math
import db
import json
import requests
import smtplib
from email.mime.text import MIMEText
import time
import pika
from pika.adapters import SelectConnection
# from openpyxl import load_workbook
from lib import *

"""
Function to print exception traceback
useful for debugging purposes
"""
def show_exception_traceback():
	exc_type, exc_value, exc_tb = sys.exc_info()
	fname = os.path.split(exc_tb.tb_frame.f_code.co_filename)[1]
	print("############################################ EXCEPTION OCCURRED ####################################################")
	print("Error Class: %s" %exc_type)
	print("Error Detail: %s " %exc_value)
	print("Filename: %s" %fname)
	print("Line number: %s " %exc_tb.tb_lineno)
	sys.exit()


"""
Import python file from an absolute path
@return: module (Python module)
"""
def absImport(pythonFile):
	try:
		import importlib.machinery
		#split the file we want to import into the path and fileName
		root, fileN = os.path.split(pythonFile)

		# strip the ".py" extension, since we only want the modulename
		fileN = fileN.rstrip(".py")
		
		# Look for the "spec" used to import the named module.
		# The "spec" contains the information needed to actually load the module
		spec = importlib.machinery.PathFinder.find_spec(fileN, path=[root])
		
		# If the module wasn't found, return false
		if not spec:
			return False
	
		# Otherwise, load the found module, and return it
		module = spec.loader.load_module()
		return module
	except Exception as e:
		show_exception_traceback()



"""
Function to parse arguments from command line
@return: args
"""
def parse_cli_args():
	import argparse
	parser = argparse.ArgumentParser(description='Main script to draw images from GPS points')
	parser.add_argument('-c','--config', help='Location of the configuration file', required=True, dest="config_loc")	
	args = parser.parse_args()

	return args

"""
Function to initialize and setup the logging functionality
"""
def init_log_file():
	with open(config.LOG.Path, 'w'):
		pass
	logging.basicConfig(filename=config.LOG.Path, level=logging.DEBUG)

"""
Function to optimize pool post treatment for team transfers between pool
"""
def optimize_pool_post_treatment_team_transfers(D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, oldResultId, userId, teamTransfers, flagPhantom, calculatedResult):
	try:
		# duplicate results
		results = calculatedResult
		
# 		logging.debug(" results: %s" %(json.dumps(results),))
		iter = config.INPUT.Iter
		logging.debug(" iter: %s" %iter)


		typeMatch = results["typeMatch"]
		logging.debug(" typeMatch: %s" %(typeMatch,))

		# add final flag to results
		if "params" in results:
# 			results["params"]["final"] = "oui"

			# change values concerning variation of team members per pool
			results["params"]["varEquipeParPoulePossible"] = 0
			
			# get team names 
			for scenario, contentScenario in teamTransfers.items():
				for teamTransfer in contentScenario:
					teamTransfer["equipeDepartNom"] = get_team_name_escaped_from_team_id(teamTransfer["equipeDepart"])
					teamTransfer["equipeDestinationNom"] = get_team_name_escaped_from_team_id(teamTransfer["equipeDestination"])
			
			# add team transfers to params
			results["params"]["changeAffectEquipes"] = teamTransfers
			
			
		########################################### Transfer team between pools #######################################
		for scenario, teamTransfers in teamTransfers.items():
			logging.debug(" scenario: %s" %(scenario,))
# 			logging.debug(" teamTransfers: %s" %(teamTransfers,))
			
			# get the corresponding data from the previous calculated result
			if scenario == "optimalSansContrainte":
				scenarioName = "scenarioOptimalSansContrainte"
			elif scenario == "equitableSansContrainte":
				scenarioName = "scenarioEquitableSansContrainte"
			elif scenario == "optimalAvecContrainte":
				scenarioName = "scenarioOptimalAvecContrainte"
			elif scenario == "equitableAvecContrainte":
				scenarioName = "scenarioEquitableAvecContrainte"

			resultsScenario = results[scenarioName]

# 			logging.debug(" before resultsScenario: \n%s" %(json.dumps(resultsScenario),))

			poulesIdOri  = resultsScenario["poulesId"]
# 			logging.debug(" poulesIdOri: %s" %(poulesIdOri,))
			
			poulesIdResult = dict(poulesIdOri)
			
			for teamTransfer in teamTransfers:
				logging.debug(" teamTransfer: %s" %(teamTransfer,))
			
				# remove and add parting team
				poulesIdResult[teamTransfer["pouleDepart"]].remove(int(teamTransfer["equipeDepart"]))
				poulesIdResult[teamTransfer["pouleDestination"]].append(int(teamTransfer["equipeDepart"]))
				
				# remove and add entering team
				poulesIdResult[teamTransfer["pouleDestination"]].remove(int(teamTransfer["equipeDestination"]))
				poulesIdResult[teamTransfer["pouleDepart"]].append(int(teamTransfer["equipeDestination"]))

				# sort leaving and destinatio pool
				poulesIdResult[teamTransfer["pouleDestination"]].sort()
				poulesIdResult[teamTransfer["pouleDepart"]].sort()
				
			
# 			logging.debug(" poulesIdResult: %s" %(poulesIdResult,))
			
			# update pool ids
			resultsScenario["poulesId"] = poulesIdResult
			

			# get coordinates for each point in the pools
			poolDistributionCoords_scenario = get_coords_pool_distribution(poulesIdResult)
			results[scenarioName]["poulesCoords"] = poolDistributionCoords_scenario
# 			logging.debug(" poolDistributionCoords_scenario: %s" %(poolDistributionCoords_scenario,))
		
			# get encounter list from pool distribution dict
			encounters_scenario = create_encounters_from_pool_distribution(poulesIdResult)
			results[scenarioName]["rencontreDetails"] = encounters_scenario
	 		
			# get pool details from encounters
			poolDetails_scenario = create_pool_details_from_encounters(encounters_scenario, poulesIdResult)
			results[scenarioName]["estimationDetails"] = poolDetails_scenario
		
			# get sum info from pool details
			sumInfo_scenario = get_sum_info_from_pool_details(poolDetails_scenario)
			results[scenarioName]["estimationGenerale"] = sumInfo_scenario
			
			
		logging.debug("" )

			
		return results

	except Exception as e:
		show_exception_traceback()


"""
Function to optimize pool post treatment for variation of team number per pool
"""	
def optimize_pool_post_treatment_var_team_nbr(D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, resultId, userId, varTeamNbrPerPool, flagPhantom, calculatedResult, P_InitMat_withConstraint, P_InitMat_oneWayWithConstraint):
	try:
		# duplicate results
		results = calculatedResult

		logging.debug(" varTeamNbrPerPool: %s" %(varTeamNbrPerPool,))

		iter = config.INPUT.Iter
		logging.debug(" iter: %s" %iter)

		typeMatch = results["typeMatch"]
		logging.debug(" typeMatch: %s" %(typeMatch,))

		if typeMatch == "allerSimple":
			isOneWay = 1
		else:
			isOneWay = 0

		# add final flag to results
		if "params" in results:
			results["params"]["final"] = "oui"

			# change values concerning variation of team members per pool
			results["params"]["varEquipeParPouleChoisi"] = varTeamNbrPerPool
			results["params"]["varEquipeParPoulePossible"] = 0
			

		############# optimal scenario without constraint #################
		logging.debug("")
		logging.debug(" ####################### RESULT OPTIMAL WITHOUT CONSTRAINT #############################################")
		resultsOptimalWithoutConstraint = results["scenarioOptimalSansContrainte"]
		if resultsOptimalWithoutConstraint:
			
			poolDistribution_OptimalWithoutConstraint = variation_team_number_per_pool(resultsOptimalWithoutConstraint["poulesId"], varTeamNbrPerPool)
			logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %(poolDistribution_OptimalWithoutConstraint,))
			
			# create P Matrix from pool distribution	
			P_Mat_OptimalWithoutConstraint = create_matrix_from_pool_distribution(poolDistribution_OptimalWithoutConstraint, teamNbr, teams)
# 			logging.debug(" P_Mat_OptimalWithoutConstraint: \n%s" %(P_Mat_OptimalWithoutConstraint,))

			# filter upper triangular size in the case of one way match
			if typeMatch == "allerSimple":
				P_Mat_OptimalWithoutConstraint = np.triu(P_Mat_OptimalWithoutConstraint)
			logging.debug(" P_Mat_OptimalWithoutConstraint.shape: \n%s" %(P_Mat_OptimalWithoutConstraint.shape,))

			for iterLaunch in range(config.INPUT.IterLaunch):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_OptimalWithoutConstraint, D_Mat, iter, teamNbr)#

			chosenDistance_OptimalWithoutConstraint = calculate_V_value(P_Mat_OptimalWithoutConstraint, D_Mat)
			logging.debug(" chosenDistance_OptimalWithoutConstraint: %s" %chosenDistance_OptimalWithoutConstraint)

	 		# get pool distribution
			poolDistribution_OptimalWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
			logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %poolDistribution_OptimalWithoutConstraint)

			# eliminate phantom teams
			poolDistribution_OptimalWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["poulesId"] = poolDistribution_OptimalWithoutConstraint
			logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %poolDistribution_OptimalWithoutConstraint)

			infoPool = get_info_pool_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
			logging.debug(" infoPool: %s" %infoPool)
			results["params"]["infoPoule"] = infoPool

			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithoutConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithoutConstraint
		
			# get encounter list from pool distribution dict
			encounters_OptimalWithoutConstraint = create_encounters_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["rencontreDetails"] = encounters_OptimalWithoutConstraint
	 		
			# get pool details from encounters
			poolDetails_OptimalWithoutConstraint = create_pool_details_from_encounters(encounters_OptimalWithoutConstraint, poolDistribution_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["estimationDetails"] = poolDetails_OptimalWithoutConstraint
		
			# get sum info from pool details
			sumInfo_OptimalWithoutConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["estimationGenerale"] = sumInfo_OptimalWithoutConstraint

		############# equitable scenario without constraint #################
		resultsEquitableWithoutConstraint = results["scenarioEquitableSansContrainte"]
		logging.debug("")
		logging.debug(" ####################### RESULT EQUITABLE WITHOUT CONSTRAINT ############################################")
		if resultsEquitableWithoutConstraint:

			poolDistribution_EquitableWithoutConstraint = variation_team_number_per_pool(resultsEquitableWithoutConstraint["poulesId"], varTeamNbrPerPool)
			logging.debug(" poolDistribution_EquitableWithoutConstraint: %s" %(poolDistribution_EquitableWithoutConstraint,))
			
			# create P Matrix from pool distribution	
			P_Mat_EquitableWithoutConstraint = create_matrix_from_pool_distribution(poolDistribution_EquitableWithoutConstraint, teamNbr, teams)

			# filter upper triangular size in the case of one way match
			if typeMatch == "allerSimple":
				P_Mat_EquitableWithoutConstraint = np.triu(P_Mat_EquitableWithoutConstraint)
			logging.debug(" P_Mat_EquitableWithoutConstraint.shape: \n%s" %(P_Mat_EquitableWithoutConstraint.shape,))

			for iterLaunch in range(config.INPUT.IterLaunch):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_EquitableWithoutConstraint, D_Mat, iter, teamNbr)#

			chosenDistance_EquitableWithoutConstraint = calculate_V_value(P_Mat_EquitableWithoutConstraint, D_Mat)
			logging.debug(" chosenDistance_EquitableWithoutConstraint: %s" %chosenDistance_EquitableWithoutConstraint)
	
			# get pool distribution
			poolDistribution_EquitableWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
			logging.debug(" poolDistribution_EquitableWithoutConstraint: %s" %poolDistribution_EquitableWithoutConstraint)
	
			# eliminate phnatom teams
			poolDistribution_EquitableWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["poulesId"] = poolDistribution_EquitableWithoutConstraint
			logging.debug(" poolDistribution_EquitableWithoutConstraint: %s" %poolDistribution_EquitableWithoutConstraint)
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithoutConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithoutConstraint
	# 		logging.debug(" poolDistributionCoords_EquitableWithoutConstraint: %s" %poolDistributionCoords_EquitableWithoutConstraint)
	
			# get encounter list from pool distribution dict
			encounters_EquitableWithoutConstraint = create_encounters_from_pool_distribution(poolDistribution_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["rencontreDetails"] = encounters_EquitableWithoutConstraint
	# 		logging.debug(" encounters_EquitableWithoutConstraint: \n%s" %encounters_EquitableWithoutConstraint)
	
			# get pool details from encounters
			poolDetails_EquitableWithoutConstraint = create_pool_details_from_encounters(encounters_EquitableWithoutConstraint, poolDistribution_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["estimationDetails"] = poolDetails_EquitableWithoutConstraint
			logging.debug(" poolDetails_EquitableWithoutConstraint: \n%s" %poolDetails_EquitableWithoutConstraint)
	
			# get sum info from pool details
			sumInfo_EquitableWithoutConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["estimationGenerale"] = sumInfo_EquitableWithoutConstraint
			logging.debug(" sumInfo_EquitableWithoutConstraint: \n%s" %sumInfo_EquitableWithoutConstraint)



		############# optimal scenario with constraint #################
		logging.debug("")
		logging.debug(" ####################### RESULT OPTIMAL WITH CONSTRAINT #############################################")
		resultsOptimalWithConstraint = results["scenarioOptimalAvecContrainte"]
		if resultsOptimalWithConstraint:

			poolDistribution_OptimalWithConstraint = variation_team_number_per_pool(resultsOptimalWithConstraint["poulesId"], varTeamNbrPerPool)
			logging.debug(" poolDistribution_OptimalWithConstraint: %s" %(poolDistribution_OptimalWithConstraint,))
			
			# create P Matrix from pool distribution	
			P_Mat_OptimalWithConstraint = create_matrix_from_pool_distribution(poolDistribution_OptimalWithConstraint, teamNbr, teams)

			# filter upper triangular size in the case of one way match
			if typeMatch == "allerSimple":
				P_Mat_OptimalWithConstraint = np.triu(P_Mat_OptimalWithConstraint)
				
			logging.debug(" P_Mat_OptimalWithConstraint.shape: \n%s" %(P_Mat_OptimalWithConstraint.shape,))
			
			for iterLaunch in range(config.INPUT.IterLaunch):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_OptimalWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#

				if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
					P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
				# in case of failure because of constraints
				else:
					if typeMatch == "allerRetour":
						P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
					elif typeMatch == "allerSimple":
						P_Mat_OptimalWithConstraint = P_InitMat_oneWayWithConstraint

			chosenDistance_OptimalWithConstraint = calculate_V_value(P_Mat_OptimalWithConstraint, D_Mat)
			logging.debug(" chosenDistance_OptimalWithConstraint: %s" %chosenDistance_OptimalWithConstraint)

			poolDistribution_OptimalWithConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithConstraint, teamNbr, poolNbr, poolSize, teams)
			logging.debug(" poolDistribution_OptimalWithConstraint: %s" %poolDistribution_OptimalWithConstraint)

			# eliminate phnatom teams
			poolDistribution_OptimalWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesId"] = poolDistribution_OptimalWithConstraint
			logging.debug(" poolDistribution_OptimalWithConstraint: %s" %poolDistribution_OptimalWithConstraint)

			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithConstraint

			# get encounter list from pool distribution dict
			encounters_OptimalWithConstraint = create_encounters_from_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["rencontreDetails"] = encounters_OptimalWithConstraint
		
			# get pool details from encounters
			poolDetails_OptimalWithConstraint = create_pool_details_from_encounters(encounters_OptimalWithConstraint, poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationDetails"] = poolDetails_OptimalWithConstraint
			logging.debug(" poolDetails_OptimalWithConstraint: \n%s" %poolDetails_OptimalWithConstraint)
	
			# get sum info from pool details
			sumInfo_OptimalWithConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationGenerale"] = sumInfo_OptimalWithConstraint
			logging.debug(" sumInfo_OptimalWithConstraint: \n%s" %sumInfo_OptimalWithConstraint)


		############# equitable scenario with constraint #################
		logging.debug("")
		logging.debug(" ######################### RESULT EQUITABLE WITH CONSTRAINT ############################################")
		resultsEquitableWithConstraint = results["scenarioEquitableAvecContrainte"]
		if resultsEquitableWithConstraint:

			poolDistribution_EquitableWithConstraint = variation_team_number_per_pool(resultsEquitableWithConstraint["poulesId"], varTeamNbrPerPool)
			logging.debug(" poolDistribution_EquitableWithConstraint: %s" %(poolDistribution_EquitableWithConstraint,))
			
			# create P Matrix from pool distribution	
			P_Mat_EquitableWithConstraint = create_matrix_from_pool_distribution(poolDistribution_EquitableWithConstraint, teamNbr, teams)

			# filter upper triangular size in the case of one way match
			if typeMatch == "allerSimple":
				P_Mat_EquitableWithConstraint = np.triu(P_Mat_EquitableWithConstraint)
			logging.debug(" P_Mat_EquitableWithConstraint.shape: \n%s" %(P_Mat_EquitableWithConstraint.shape,))

			for iterLaunch in range(config.INPUT.IterLaunch):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_EquitableWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#

				if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
					P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
				# in case of failure because of constraints
				else:
					if typeMatch == "allerRetour":
						P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
					elif typeMatch == "allerSimple":
						P_Mat_EquitableWithConstraint = P_InitMat_oneWayWithConstraint




			chosenDistance_EquitableWithConstraint = calculate_V_value(P_Mat_EquitableWithConstraint, D_Mat)
			logging.debug(" chosenDistance_EquitableWithConstraint: %s" %chosenDistance_EquitableWithConstraint)
	
			# get pool distribution
			poolDistribution_EquitableWithConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithConstraint, teamNbr, poolNbr, poolSize, teams)
			logging.debug(" poolDistribution_EquitableWithConstraint: %s" %poolDistribution_EquitableWithConstraint)
	
			# eliminate phnatom teams
			poolDistribution_EquitableWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesId"] = poolDistribution_EquitableWithConstraint
			logging.debug(" poolDistribution_EquitableWithConstraint: %s" %poolDistribution_EquitableWithConstraint)
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithConstraint
# 			logging.debug(" poolDistributionCoords_EquitableWithConstraint: %s" %poolDistributionCoords_EquitableWithConstraint)

			# get encounter list from pool distribution dict
			encounters_EquitableWithConstraint = create_encounters_from_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["rencontreDetails"] = encounters_EquitableWithConstraint
# 			logging.debug(" encounters_EquitableWithConstraint: %s" %encounters_EquitableWithConstraint)
	
			# get pool details from encounters
			poolDetails_EquitableWithConstraint = create_pool_details_from_encounters(encounters_EquitableWithConstraint, poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationDetails"] = poolDetails_EquitableWithConstraint
			logging.debug(" poolDetails_EquitableWithConstraint: \n%s" %poolDetails_EquitableWithConstraint)
	
			# get sum info from pool details
			sumInfo_EquitableWithConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationGenerale"] = sumInfo_EquitableWithConstraint
			logging.debug(" sumInfo_EquitableWithConstraint: \n%s" %sumInfo_EquitableWithConstraint)


		return results

	except Exception as e:
		show_exception_traceback()
	
		
"""
Function to optimize pool for Round Trip Match (Match Aller Retour)
"""
def optimize_pool_round_trip_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom):
	try:
		results = {"typeMatch": "allerRetour", "nombrePoule": poolNbr, "taillePoule": poolSize, 
					"scenarioRef": {}, "scenarioOptimalSansContrainte": {}, "scenarioOptimalAvecContrainte": {}, 
					"scenarioEquitableSansContrainte": {}, "scenarioEquitableAvecContrainte": {}, "params": {}
					}

		isOneWay = 0

# 		# get list of ids, names and cities from entity table for prohibition constraints
		for indexProhibition, members in enumerate(prohibitionConstraints, start=1):
# 			logging.debug(" indexProhibition: %s" %indexProhibition)
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
# 			logging.debug(" prohibitionDetail: %s" %prohibitionDetail)
			
			if "interdictions" not in results["params"]:
				results["params"]["interdictions"] = {indexProhibition: {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}
													}
			else: 
				results["params"]["interdictions"][indexProhibition] = {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}

		# get list of names and cities from entity table for type distribution constraints
		for teamType, members in typeDistributionConstraints.items():
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
			
			if "repartitionsHomogenes" not in results["params"]:
				results["params"]["repartitionsHomogenes"] = {teamType: { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 
															}
			else:
				results["params"]["repartitionsHomogenes"][teamType] = { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 
				
# 		logging.debug(" results: %s" %(json.dumps(results["params"]["repartitionsHomogenes"]),))

		# save constraint variation of team number per pool
		results["params"]["varEquipeParPouleChoisi"] = varTeamNbrPerPool

		# based on phantom flag, save to results the possibility to make variation of team number per pool
		if flagPhantom:
			results["params"]["phantomExiste"] = 1
			results["params"]["varEquipeParPoulePossible"] = 0
			results["params"]["varEquipeParPouleProposition"] = [0]
			
		else:
			results["params"]["phantomExiste"] = 0
			results["params"]["varEquipeParPoulePossible"] = 1
			maxVarTeamNbrPerPool = poolSize - 2
			results["params"]["varEquipeParPouleProposition"] = list(range(0, maxVarTeamNbrPerPool+1 ))
			# limit variation of team member to max 2
			if len(results["params"]["varEquipeParPouleProposition"]) > 3:
				results["params"]["varEquipeParPouleProposition"] = results["params"]["varEquipeParPouleProposition"][:3]


		logging.debug(" ########################################## ROUND TRIP　MATCH ###############################################")
		iter = config.INPUT.Iter
		logging.debug(" iter: %s" %iter)
		
		# add status constraints in the result
		if statusConstraints:
			results["contraintsExiste"] = 1
		else:
			results["contraintsExiste"] = 0


		logging.debug("")
		logging.debug(" #################################### REFERENCE RESULT #################################################")
		returnPoolDistributionRef = create_reference_pool_distribution_from_db(teams, poolSize)
		
		# process only if there is a reference
		if returnPoolDistributionRef["status"] == "yes":
			
			# add boolean to results
			results["refExiste"] = 1
			
			poolDistributionRef = returnPoolDistributionRef["data"]
			logging.debug(" poolDistributionRef: \n%s" %poolDistributionRef)

			# create P Matrix reference to calculate distance	
			P_Mat_ref = create_matrix_from_pool_distribution(poolDistributionRef, teamNbr, teams)
			logging.debug(" P_Mat_ref.shape: \n%s" %(P_Mat_ref.shape,))
	# 		logging.debug(" P_Mat_ref: \n%s" %(P_Mat_ref,))
	
			chosenDistanceRef = calculate_V_value(P_Mat_ref, D_Mat)
			logging.debug(" chosenDistanceRef: %s" %chosenDistanceRef)
	
			# eliminate phnatom teams
			poolDistributionRef = eliminate_phantom_in_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesId"] = poolDistributionRef
			logging.debug(" poolDistributionRef: %s" %poolDistributionRef)
	
			# get coordinates for each point in the pools
			poolDistributionCoordsRef = get_coords_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesCoords"] = poolDistributionCoordsRef
# 			logging.debug(" poolDistributionCoordsRef: %s" %poolDistributionCoordsRef)
	
			# get encounter list from pool distribution dict
			encountersRef = create_encounters_from_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["rencontreDetails"] = encountersRef
	
			# get pool details from encounters
			poolDetailsRef = create_pool_details_from_encounters(encountersRef, poolDistributionRef)
			results["scenarioRef"]["estimationDetails"] = poolDetailsRef
			logging.debug(" poolDetailsRef: \n%s" %poolDetailsRef)
	
			# get sum info from pool details
			sumInfoRef = get_sum_info_from_pool_details(poolDetailsRef)
			results["scenarioRef"]["estimationGenerale"] = sumInfoRef
			logging.debug(" sumInfoRef: \n%s" %sumInfoRef)
		else:
			# add boolean to results
			results["refExiste"] = 0


		logging.debug("")
		logging.debug(" ####################### RESULT OPTIMAL WITHOUT CONSTRAINT #############################################")

		# optimal scenario without constraint
		for iterLaunch in range(config.INPUT.IterLaunch):
			logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
			# launch calculation based on ref scenario only if the params are comparable
			if iterLaunch == 0:
				if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)#
				else:
					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
			else:
				P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_OptimalWithoutConstraint, D_Mat, iter, teamNbr)#
# 
# 		P_Mat_OptimalWithoutConstraint = P_Mats_OptimalWithoutConstraint[P_Mat_chosenIndex]
		chosenDistance_OptimalWithoutConstraint = calculate_V_value(P_Mat_OptimalWithoutConstraint, D_Mat)
		logging.debug(" chosenDistance_OptimalWithoutConstraint: %s" %chosenDistance_OptimalWithoutConstraint)
# 	
	
# 		# get pool distribution
		poolDistribution_OptimalWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
		logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %poolDistribution_OptimalWithoutConstraint)
# 		
		# eliminate phnatom teams
		poolDistribution_OptimalWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesId"] = poolDistribution_OptimalWithoutConstraint
		logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %poolDistribution_OptimalWithoutConstraint)
		
		# get real info without phantom teams (only for optimal scenario without constraint)
		infoPool = get_info_pool_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		logging.debug(" infoPool: %s" %infoPool)
		results["params"]["infoPoule"] = infoPool
		
		# get coordinates for each point in the pools
		poolDistributionCoords_OptimalWithoutConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithoutConstraint
# 		logging.debug(" poolDistributionCoords_OptimalWithoutConstraint: %s" %poolDistributionCoords_OptimalWithoutConstraint)
		
		# get encounter list from pool distribution dict
		encounters_OptimalWithoutConstraint = create_encounters_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["rencontreDetails"] = encounters_OptimalWithoutConstraint
# 		logging.debug(" encounters_OptimalWithoutConstraint: \n%s" %encounters_OptimalWithoutConstraint)
 		
		# get pool details from encounters
		poolDetails_OptimalWithoutConstraint = create_pool_details_from_encounters(encounters_OptimalWithoutConstraint, poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationDetails"] = poolDetails_OptimalWithoutConstraint
		logging.debug(" poolDetails_OptimalWithoutConstraint: \n%s" %poolDetails_OptimalWithoutConstraint)
	
		# get sum info from pool details
		sumInfo_OptimalWithoutConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationGenerale"] = sumInfo_OptimalWithoutConstraint
		logging.debug(" sumInfo_OptimalWithoutConstraint: \n%s" %sumInfo_OptimalWithoutConstraint)


		logging.debug("")
		logging.debug(" ####################### RESULT EQUITABLE WITHOUT CONSTRAINT ############################################")
		# equitable scenario without constraint
		# launch calculation based on ref scenario only if the params are comparable
		for iterLaunch in range(config.INPUT.IterLaunch):
			logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
			# launch calculation based on ref scenario only if the params are comparable
			if iterLaunch == 0:
				if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)#
				else:
					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
			else:
				P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_EquitableWithoutConstraint, D_Mat, iter, teamNbr)#

		chosenDistance_EquitableWithoutConstraint = calculate_V_value(P_Mat_EquitableWithoutConstraint, D_Mat)
		logging.debug(" chosenDistance_EquitableWithoutConstraint: %s" %chosenDistance_EquitableWithoutConstraint)

		np.savetxt("/tmp/p_mat_equitable_without_constraint.csv", P_Mat_EquitableWithoutConstraint, delimiter=",", fmt='%d') # DEBUG

		# get pool distribution
# 		poolDistribution_EquitableWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithoutConstraint, teamNbr, poolNbr, poolSize, teams, varTeamNbrPerPool)
		poolDistribution_EquitableWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
		logging.debug(" poolDistribution_EquitableWithoutConstraint: %s" %poolDistribution_EquitableWithoutConstraint)

		# eliminate phnatom teams
		poolDistribution_EquitableWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesId"] = poolDistribution_EquitableWithoutConstraint
		logging.debug(" poolDistribution_EquitableWithoutConstraint: %s" %poolDistribution_EquitableWithoutConstraint)

		# get coordinates for each point in the pools
		poolDistributionCoords_EquitableWithoutConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithoutConstraint
# 		logging.debug(" poolDistributionCoords_EquitableWithoutConstraint: %s" %poolDistributionCoords_EquitableWithoutConstraint)

		# get encounter list from pool distribution dict
		encounters_EquitableWithoutConstraint = create_encounters_from_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["rencontreDetails"] = encounters_EquitableWithoutConstraint
# 		logging.debug(" encounters_EquitableWithoutConstraint: \n%s" %encounters_EquitableWithoutConstraint)

		# get pool details from encounters
		poolDetails_EquitableWithoutConstraint = create_pool_details_from_encounters(encounters_EquitableWithoutConstraint, poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationDetails"] = poolDetails_EquitableWithoutConstraint
		logging.debug(" poolDetails_EquitableWithoutConstraint: \n%s" %poolDetails_EquitableWithoutConstraint)

		# get sum info from pool details
		sumInfo_EquitableWithoutConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationGenerale"] = sumInfo_EquitableWithoutConstraint
		logging.debug(" sumInfo_EquitableWithoutConstraint: \n%s" %sumInfo_EquitableWithoutConstraint)


		if statusConstraints:
			logging.debug("")
			logging.debug(" ####################### RESULT OPTIMAL WITH CONSTRAINT #############################################")
			# optimal scenario with constraint   
			for iterLaunch in range(config.INPUT.IterLaunch):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					# try to use ref scenario
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						else:
							# if error, launch again with P_init_matrix
							P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
								P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
								
					# if there is no ref scenario
					else:
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
				
				# for second iteration onwards
				else:
					P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_OptimalWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
						P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
					# if error, use P_init_matrix
					else:
						P_Mat_OptimalWithConstraint = P_InitMat_withConstraint



					
			chosenDistance_OptimalWithConstraint = calculate_V_value(P_Mat_OptimalWithConstraint, D_Mat)
			logging.debug(" chosenDistance_OptimalWithConstraint: %s" %chosenDistance_OptimalWithConstraint)
	 	
			np.savetxt("/tmp/p_mat_optimal_with_constraint.csv", P_Mat_OptimalWithConstraint, delimiter=",", fmt='%d') # DEBUG
	
# 			poolDistribution_OptimalWithConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithConstraint, teamNbr, poolNbr, poolSize, teams, varTeamNbrPerPool)
			poolDistribution_OptimalWithConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithConstraint, teamNbr, poolNbr, poolSize, teams)
			logging.debug(" poolDistribution_OptimalWithConstraint: %s" %poolDistribution_OptimalWithConstraint)
	
			# eliminate phnatom teams
			poolDistribution_OptimalWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesId"] = poolDistribution_OptimalWithConstraint
			logging.debug(" poolDistribution_OptimalWithConstraint: %s" %poolDistribution_OptimalWithConstraint)
	
			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithConstraint
# 			logging.debug(" poolDistributionCoords_OptimalWithConstraint: %s" %poolDistributionCoords_OptimalWithConstraint)

	
			# get encounter list from pool distribution dict
			encounters_OptimalWithConstraint = create_encounters_from_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["rencontreDetails"] = encounters_OptimalWithConstraint
# 			logging.debug(" encounters_OptimalWithoutConstraint: \n%s" %encounters_OptimalWithoutConstraint)
			
			# get pool details from encounters
			poolDetails_OptimalWithConstraint = create_pool_details_from_encounters(encounters_OptimalWithConstraint, poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationDetails"] = poolDetails_OptimalWithConstraint
			logging.debug(" poolDetails_OptimalWithConstraint: \n%s" %poolDetails_OptimalWithConstraint)
		
			# get sum info from pool details
			sumInfo_OptimalWithConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationGenerale"] = sumInfo_OptimalWithConstraint
			logging.debug(" sumInfo_OptimalWithConstraint: \n%s" %sumInfo_OptimalWithConstraint)

			logging.debug("")
			logging.debug(" ######################### RESULT EQUITABLE WITH CONSTRAINT ############################################")
	
			# equitable scenario without constraint
			for iterLaunch in range(config.INPUT.IterLaunch):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable

				if iterLaunch == 0:
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						# try to use ref scenario
						P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						else:
							# if error, launch again with P_init_matrix
							P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
								P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
					
					
					# if there is no ref scenario
					else:
						P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
				
				
				# for second iteration onwards
				else:
					P_Mat_EquitableWithConstraint = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_EquitableWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
						P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
					# if error, use P_init_matrix
					else:
						P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
					
	
					
	
	
			chosenDistance_EquitableWithConstraint = calculate_V_value(P_Mat_EquitableWithConstraint, D_Mat)
			logging.debug(" chosenDistance_EquitableWithConstraint: %s" %chosenDistance_EquitableWithConstraint)
	
			np.savetxt("/tmp/p_mat_equitable_with_constraint.csv", P_Mat_EquitableWithConstraint, delimiter=",", fmt='%d') # DEBUG
	
			# get pool distribution
			poolDistribution_EquitableWithConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithConstraint, teamNbr, poolNbr, poolSize, teams)
			logging.debug(" poolDistribution_EquitableWithConstraint: %s" %poolDistribution_EquitableWithConstraint)
	
			# eliminate phnatom teams
			poolDistribution_EquitableWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesId"] = poolDistribution_EquitableWithConstraint
			logging.debug(" poolDistribution_EquitableWithConstraint: %s" %poolDistribution_EquitableWithConstraint)
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithConstraint
# 			logging.debug(" poolDistributionCoords_EquitableWithConstraint: %s" %poolDistributionCoords_EquitableWithConstraint)

			# get encounter list from pool distribution dict
			encounters_EquitableWithConstraint = create_encounters_from_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["rencontreDetails"] = encounters_EquitableWithConstraint
# 			logging.debug(" encounters_EquitableWithConstraint: %s" %encounters_EquitableWithConstraint)
	
			# get pool details from encounters
			poolDetails_EquitableWithConstraint = create_pool_details_from_encounters(encounters_EquitableWithConstraint, poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationDetails"] = poolDetails_EquitableWithConstraint
			logging.debug(" poolDetails_EquitableWithConstraint: \n%s" %poolDetails_EquitableWithConstraint)
	
			# get sum info from pool details
			sumInfo_EquitableWithConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationGenerale"] = sumInfo_EquitableWithConstraint
			logging.debug(" sumInfo_EquitableWithConstraint: \n%s" %sumInfo_EquitableWithConstraint)


# 		logging.debug(" results: \n%s" %results)

		return results 
	except Exception as e:
		show_exception_traceback()

"""
Function to optimize pool for One Way Match (Match Aller Simple)
"""
def optimize_pool_one_way_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom):
	try:
		results = {"typeMatch": "allerSimple", "nombrePoule": poolNbr, "taillePoule": poolSize, 
					"scenarioRef": {}, "scenarioOptimalSansContrainte": {}, "scenarioOptimalAvecContrainte": {}, 
					"scenarioEquitableSansContrainte": {}, "scenarioEquitableAvecContrainte": {}, "params": {}
				}
		isOneWay = 1

# 		# get list of ids, names and cities from entity table for prohibition constraints
		for indexProhibition, members in enumerate(prohibitionConstraints, start=1):
# 			logging.debug(" indexProhibition: %s" %indexProhibition)
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
# 			logging.debug(" prohibitionDetail: %s" %prohibitionDetail)
			
			if "interdictions" not in results["params"]:
				results["params"]["interdictions"] = {indexProhibition: {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}
													}
			else: 
				results["params"]["interdictions"][indexProhibition] = {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}

		# get list of names and cities from entity table for type distribution constraints
		for teamType, members in typeDistributionConstraints.items():
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
			
			if "repartitionsHomogenes" not in results["params"]:
				results["params"]["repartitionsHomogenes"] = {teamType: { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 
															}
			else:
				results["params"]["repartitionsHomogenes"][teamType] = { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 

		# save constraint variation of team number per pool
		results["params"]["varEquipeParPouleChoisi"] = varTeamNbrPerPool

		# based on phantom flag, save to results the possibility to make variation of team number per pool
		if flagPhantom:
			results["params"]["phantomExiste"] = 1
			results["params"]["varEquipeParPoulePossible"] = 0
			results["params"]["varEquipeParPouleProposition"] = [0]
		else:
			results["params"]["phantomExiste"] = 0
			results["params"]["varEquipeParPoulePossible"] = 1
			maxVarTeamNbrPerPool = poolSize - 2
			results["params"]["varEquipeParPouleProposition"] = list(range(0, maxVarTeamNbrPerPool+1 ))
			# limit variation of team member to max 2
			if len(results["params"]["varEquipeParPouleProposition"]) > 3:
				results["params"]["varEquipeParPouleProposition"] = results["params"]["varEquipeParPouleProposition"][:3]


		logging.debug(" ########################################## ONE WAY　MATCH ###############################################")
		iter = config.INPUT.Iter
		logging.debug(" iter: %s" %iter)
		
		# add status constraints in the result
		if statusConstraints:
			results["contraintsExiste"] = 1
# 			results["params"]["contraintsExiste"] = 1
		else:
			results["contraintsExiste"] = 0
# 			results["params"]["contraintsExiste"] = 0
		
		logging.debug("")
		logging.debug(" #################################### REFERENCE RESULT #################################################")
		returnPoolDistributionRef = create_reference_pool_distribution_from_db(teams, poolSize)
		
		# process only if there is a reference
		if returnPoolDistributionRef["status"] == "yes":
			
			# add boolean to results
# 			results["params"]["refExiste"] = 1
			results["refExiste"] = 1

			poolDistributionRef = returnPoolDistributionRef["data"]
			logging.debug(" poolDistributionRef: \n%s" %poolDistributionRef)

			# create P Matrix reference to calculate distance	
			P_Mat_ref = create_matrix_from_pool_distribution(poolDistributionRef, teamNbr, teams)
			logging.debug(" P_Mat_ref.shape: \n%s" %(P_Mat_ref.shape,))
# 			logging.debug(" P_Mat_ref: \n%s" %(P_Mat_ref,))
# 			np.savetxt("/tmp/p_mat_ref_one_way.csv", P_Mat_ref, delimiter=",", fmt='%d') # DEBUG
	
			# take upper part of matrix
			P_Mat_ref = np.triu(P_Mat_ref)
# 			np.savetxt("/tmp/p_mat_ref_one_way2.csv", P_Mat_ref, delimiter=",", fmt='%d') # DEBUG
	
# 			logging.debug(" P_Mat_ref: \n%s" %(P_Mat_ref,))
			chosenDistanceRef = calculate_V_value(P_Mat_ref, D_Mat)
			logging.debug(" chosenDistanceRef: %s" %chosenDistanceRef)
	
			# eliminate phnatom teams
			poolDistributionRef = eliminate_phantom_in_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesId"] = poolDistributionRef
			logging.debug(" poolDistributionRef: %s" %poolDistributionRef)
	
			# get coordinates for each point in the pools
			poolDistributionCoordsRef = get_coords_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesCoords"] = poolDistributionCoordsRef
# 			logging.debug(" poolDistributionCoordsRef: %s" %poolDistributionCoordsRef)
	
			# get encounter list from pool distribution dict
			encountersRef = create_encounters_from_pool_distribution_one_way(poolDistributionRef)
			results["scenarioRef"]["rencontreDetails"] = encountersRef
# 			logging.debug(" encountersRef: %s" %encountersRef)
	
			# get pool details from encounters
			poolDetailsRef = create_pool_details_from_encounters(encountersRef, poolDistributionRef)
			results["scenarioRef"]["estimationDetails"] = poolDetailsRef
			logging.debug(" poolDetailsRef: \n%s" %poolDetailsRef)
	
			# get sum info from pool details
			sumInfoRef = get_sum_info_from_pool_details(poolDetailsRef)
			results["scenarioRef"]["estimationGenerale"] = sumInfoRef
			logging.debug(" sumInfoRef: \n%s" %sumInfoRef)
		else:
			# add boolean to results
# 			results["params"]["refExiste"] = 0
			results["refExiste"] = 0

		logging.debug("")
		logging.debug(" ####################### RESULT OPTIMAL WITHOUT CONSTRAINT #############################################")

		# optimal scenario without constraint
		for iterLaunch in range(config.INPUT.IterLaunch):
			logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
			# launch calculation based on ref scenario only if the params are comparable
			if iterLaunch == 0:
				if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)
				else:
					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
			else:
				P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_OptimalWithoutConstraint, D_Mat, iter, teamNbr)#
					
					
# 		np.savetxt("/tmp/p_mat_optimal_without_constraint_one_way.csv", P_Mat_OptimalWithoutConstraint, delimiter=",", fmt='%d') # DEBUG

		chosenDistance_OptimalWithoutConstraint = calculate_V_value(P_Mat_OptimalWithoutConstraint, D_Mat)
		logging.debug(" chosenDistance_OptimalWithoutConstraint: %s" %chosenDistance_OptimalWithoutConstraint)
	
# 		# get pool distribution
		poolDistribution_OptimalWithoutConstraint = create_pool_distribution_from_matrix_one_way(P_Mat_OptimalWithoutConstraint, teamNbr, poolNbr, poolSize, teams )
		logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %poolDistribution_OptimalWithoutConstraint)
# 		
		# eliminate phnatom teams
		poolDistribution_OptimalWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesId"] = poolDistribution_OptimalWithoutConstraint
		logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %poolDistribution_OptimalWithoutConstraint)
		
		# get real info without phantom teams (only for optimal scenario without constraint)
		infoPool = get_info_pool_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		logging.debug(" infoPool: %s" %infoPool)
		results["params"]["infoPoule"] = infoPool
		
		
		# get coordinates for each point in the pools
		poolDistributionCoords_OptimalWithoutConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithoutConstraint
# 		logging.debug(" poolDistributionCoords_OptimalWithoutConstraint: %s" %poolDistributionCoords_OptimalWithoutConstraint)
		
		# get encounter list from pool distribution dict
		encounters_OptimalWithoutConstraint = create_encounters_from_pool_distribution_one_way(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["rencontreDetails"] = encounters_OptimalWithoutConstraint
# 		logging.debug(" encounters_OptimalWithoutConstraint: \n%s" %encounters_OptimalWithoutConstraint)
		
		# get pool details from encounters
		poolDetails_OptimalWithoutConstraint = create_pool_details_from_encounters(encounters_OptimalWithoutConstraint, poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationDetails"] = poolDetails_OptimalWithoutConstraint
		logging.debug(" poolDetails_OptimalWithoutConstraint: \n%s" %poolDetails_OptimalWithoutConstraint)
	
		# get sum info from pool details
		sumInfo_OptimalWithoutConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationGenerale"] = sumInfo_OptimalWithoutConstraint
		logging.debug(" sumInfo_OptimalWithoutConstraint: \n%s" %sumInfo_OptimalWithoutConstraint)

		logging.debug("")
		logging.debug(" ####################### RESULT EQUITABLE WITHOUT CONSTRAINT ############################################")
		# equitable scenario without constraint

		for iterLaunch in range(config.INPUT.IterLaunch):
			logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
			# launch calculation based on ref scenario only if the params are comparable
			if iterLaunch == 0:
				# launch calculation based on ref scenario only if the params are comparable
				if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)
				else:
					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
			else:
				P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_EquitableWithoutConstraint, D_Mat, iter, teamNbr)#
				
# 		np.savetxt("/tmp/p_mat_equitable_without_constraint.csv", P_Mat_EquitableWithoutConstraint, delimiter=",", fmt='%d') # DEBUG

		chosenDistance_EquitableWithoutConstraint = calculate_V_value(P_Mat_EquitableWithoutConstraint, D_Mat)
		logging.debug(" chosenDistance_EquitableWithoutConstraint: %s" %chosenDistance_EquitableWithoutConstraint)


		# get pool distribution
		poolDistribution_EquitableWithoutConstraint = create_pool_distribution_from_matrix_one_way(P_Mat_EquitableWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
		logging.debug(" poolDistribution_EquitableWithoutConstraint: %s" %poolDistribution_EquitableWithoutConstraint)

		# eliminate phnatom teams
		poolDistribution_EquitableWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesId"] = poolDistribution_EquitableWithoutConstraint
		logging.debug(" poolDistribution_EquitableWithoutConstraint: %s" %poolDistribution_EquitableWithoutConstraint)

		# get coordinates for each point in the pools
		poolDistributionCoords_EquitableWithoutConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithoutConstraint
# 		logging.debug(" poolDistributionCoords_EquitableWithoutConstraint: %s" %poolDistributionCoords_EquitableWithoutConstraint)

		# get encounter list from pool distribution dict
		encounters_EquitableWithoutConstraint = create_encounters_from_pool_distribution_one_way(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["rencontreDetails"] = encounters_EquitableWithoutConstraint
# 		logging.debug(" encounters_EquitableWithoutConstraint: \n%s" %encounters_EquitableWithoutConstraint)

		# get pool details from encounters
		poolDetails_EquitableWithoutConstraint = create_pool_details_from_encounters(encounters_EquitableWithoutConstraint, poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationDetails"] = poolDetails_EquitableWithoutConstraint
		logging.debug(" poolDetails_EquitableWithoutConstraint: \n%s" %poolDetails_EquitableWithoutConstraint)

		# get sum info from pool details
		sumInfo_EquitableWithoutConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationGenerale"] = sumInfo_EquitableWithoutConstraint
		logging.debug(" sumInfo_EquitableWithoutConstraint: \n%s" %sumInfo_EquitableWithoutConstraint)


		if statusConstraints:
			logging.debug("")
			logging.debug(" ####################### RESULT OPTIMAL WITH CONSTRAINT #############################################")
			# optimal scenario with constraint   
			for iterLaunch in range(config.INPUT.IterLaunch):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					# try to use ref scenario
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						else:
							# if error, launch again with P_init_matrix
							P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
								P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
								
					# if there is no ref scenario
					else:
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
				
				# for second iteration onwards
				else:
					P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_OptimalWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
						P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
					# if error, use P_init_matrix
					else:
						P_Mat_OptimalWithConstraint = P_InitMat_withConstraint




			chosenDistance_OptimalWithConstraint = calculate_V_value(P_Mat_OptimalWithConstraint, D_Mat)
			logging.debug(" chosenDistance_OptimalWithConstraint: %s" %chosenDistance_OptimalWithConstraint)
	 	
			np.savetxt("/tmp/p_mat_optimal_with_constraint.csv", P_Mat_OptimalWithConstraint, delimiter=",", fmt='%d') # DEBUG
	
			poolDistribution_OptimalWithConstraint = create_pool_distribution_from_matrix_one_way(P_Mat_OptimalWithConstraint, teamNbr, poolNbr, poolSize, teams)
			logging.debug(" poolDistribution_OptimalWithConstraint: %s" %poolDistribution_OptimalWithConstraint)
	
				# eliminate phnatom teams
			poolDistribution_OptimalWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesId"] = poolDistribution_OptimalWithConstraint
			logging.debug(" poolDistribution_OptimalWithConstraint: %s" %poolDistribution_OptimalWithConstraint)
	
			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithConstraint
# 			logging.debug(" poolDistributionCoords_OptimalWithConstraint: %s" %poolDistributionCoords_OptimalWithConstraint)

			# get encounter list from pool distribution dict
			encounters_OptimalWithConstraint = create_encounters_from_pool_distribution_one_way(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["rencontreDetails"] = encounters_OptimalWithConstraint
# 			logging.debug(" encounters_OptimalWithoutConstraint: \n%s" %encounters_OptimalWithoutConstraint)
			
			# get pool details from encounters
			poolDetails_OptimalWithConstraint = create_pool_details_from_encounters(encounters_OptimalWithConstraint, poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationDetails"] = poolDetails_OptimalWithConstraint
			logging.debug(" poolDetails_OptimalWithConstraint: \n%s" %poolDetails_OptimalWithConstraint)
		
			# get sum info from pool details
			sumInfo_OptimalWithConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationGenerale"] = sumInfo_OptimalWithConstraint
			logging.debug(" sumInfo_OptimalWithConstraint: \n%s" %sumInfo_OptimalWithConstraint)

			logging.debug("")
			logging.debug(" ######################### RESULT EQUITABLE WITH CONSTRAINT ############################################")
	
			# equitable scenario without constraint
			for iterLaunch in range(config.INPUT.IterLaunch):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						# try to use ref scenario
						P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						else:
							# if error, launch again with P_init_matrix
							P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
								P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
					
					
					# if there is no ref scenario
					else:
						P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
				
				
				# for second iteration onwards
				else:
					P_Mat_EquitableWithConstraint = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_EquitableWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
						P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
					# if error, use P_init_matrix
					else:
						P_Mat_EquitableWithConstraint = P_InitMat_withConstraint


	
			chosenDistance_EquitableWithConstraint = calculate_V_value(P_Mat_EquitableWithConstraint, D_Mat)
			logging.debug(" chosenDistance_EquitableWithConstraint: %s" %chosenDistance_EquitableWithConstraint)
	
			np.savetxt("/tmp/p_mat_equitable_with_constraint.csv", P_Mat_EquitableWithConstraint, delimiter=",", fmt='%d') # DEBUG
	
			# get pool distribution
			poolDistribution_EquitableWithConstraint = create_pool_distribution_from_matrix_one_way(P_Mat_EquitableWithConstraint, teamNbr, poolNbr, poolSize, teams)
			logging.debug(" poolDistribution_EquitableWithConstraint: %s" %poolDistribution_EquitableWithConstraint)

			# eliminate phnatom teams
			poolDistribution_EquitableWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesId"] = poolDistribution_EquitableWithConstraint
			logging.debug(" poolDistribution_EquitableWithConstraint: %s" %poolDistribution_EquitableWithConstraint)
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithConstraint
# 			logging.debug(" poolDistributionCoords_EquitableWithConstraint: %s" %poolDistributionCoords_EquitableWithConstraint)

			# get encounter list from pool distribution dict
			encounters_EquitableWithConstraint = create_encounters_from_pool_distribution_one_way(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["rencontreDetails"] = encounters_EquitableWithConstraint
# 			logging.debug(" encounters_EquitableWithConstraint: %s" %encounters_EquitableWithConstraint)
	
			# get pool details from encounters
			poolDetails_EquitableWithConstraint = create_pool_details_from_encounters(encounters_EquitableWithConstraint, poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationDetails"] = poolDetails_EquitableWithConstraint
			logging.debug(" poolDetails_EquitableWithConstraint: \n%s" %poolDetails_EquitableWithConstraint)
	
			# get sum info from pool details
			sumInfo_EquitableWithConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationGenerale"] = sumInfo_EquitableWithConstraint
			logging.debug(" sumInfo_EquitableWithConstraint: \n%s" %sumInfo_EquitableWithConstraint)



		return results
	except Exception as e:
		show_exception_traceback()


"""
Function to optimize pool for Plateau Match (Match Plateau)
"""
def optimize_pool_plateau_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom, welcomeConstraintExistMatchPlateau):
	try:
		results = {"typeMatch": "plateau", "nombrePoule": poolNbr, "taillePoule": poolSize, 
					"scenarioRef": {}, "scenarioOptimalSansContrainte": {}, "scenarioOptimalAvecContrainte": {}, 
					"scenarioEquitableSansContrainte": {}, "scenarioEquitableAvecContrainte": {}, 
					"params": {"contrainteAccueilPlateauExiste" : welcomeConstraintExistMatchPlateau}
				}
		isOneWay = 0
		
		
# 		# get list of ids, names and cities from entity table for prohibition constraints
		for indexProhibition, members in enumerate(prohibitionConstraints, start=1):
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
# 			logging.debug(" prohibitionDetail: %s" %prohibitionDetail)
			
			if "interdictions" not in results["params"]:
				results["params"]["interdictions"] = {indexProhibition: {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}
													}
			else: 
				results["params"]["interdictions"][indexProhibition] = {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}

		# get list of names and cities from entity table for type distribution constraints
		for teamType, members in typeDistributionConstraints.items():
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
			
			if "repartitionsHomogenes" not in results["params"]:
				results["params"]["repartitionsHomogenes"] = {teamType: { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 
															}
			else:
				results["params"]["repartitionsHomogenes"][teamType] = { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 

		# save constraint variation of team number per pool
		results["params"]["varEquipeParPouleChoisi"] = varTeamNbrPerPool

		# based on phantom flag, save to results the possibility to make variation of team number per pool
		if flagPhantom:
			results["params"]["phantomExiste"] = 1
			results["params"]["varEquipeParPoulePossible"] = 0
			results["params"]["varEquipeParPouleProposition"] = [0]
			
		else:
			results["params"]["phantomExiste"] = 0
			results["params"]["varEquipeParPoulePossible"] = 0
			results["params"]["varEquipeParPouleProposition"] = [0]

		logging.debug(" ########################################## PLATEAU　MATCH ###############################################")

		iter = config.INPUT.Iter
		logging.debug(" iter: %s" %iter)
		
		# add status constraints in the result
		if statusConstraints:
			results["contraintsExiste"] = 1
		else:
			results["contraintsExiste"] = 0

		logging.debug("")
		logging.debug(" #################################### REFERENCE RESULT #################################################")
	
		# get info for reference scenario from DB
		returnRefScenarioPlateau =  get_ref_scenario_plateau(teams, userId, reportId)
# 		logging.debug("returnRefScenarioPlateau: \n%s"%returnRefScenarioPlateau)

		returnPoolDistributionRef = create_reference_pool_distribution_from_db(teams, poolSize)
		
		
		# process only if there is a reference
		if returnRefScenarioPlateau["status"] == "yes" and  returnPoolDistributionRef["status"] == "yes":
			
			# add boolean to results
# 			results["params"]["refExiste"] = 1
			results["refExiste"] = 1

			encountersRefPlateau = returnRefScenarioPlateau["data"]
			results["scenarioRef"]["rencontreDetails"] = encountersRefPlateau
# 			logging.debug(" encountersRefPlateau: \n%s" %json.dumps(encountersRefPlateau))


			poolDistributionRef = returnPoolDistributionRef["data"]
# 			logging.debug(" poolDistributionRef: \n%s" %poolDistributionRef)

			chosenDistanceRefPlateau = calculate_distance_from_encounters_plateau(encountersRefPlateau)
			logging.debug(" chosenDistanceRefPlateau: %s" %chosenDistanceRefPlateau)
# 	

			# create P Matrix reference to calculate distance	
			P_Mat_ref = create_matrix_from_pool_distribution(poolDistributionRef, teamNbr, teams)
			logging.debug(" P_Mat_ref.shape: \n%s" %(P_Mat_ref.shape,))
	# 		logging.debug(" P_Mat_ref: \n%s" %(P_Mat_ref,))

			chosenDistanceRefPool = calculate_V_value(P_Mat_ref, D_Mat)
			logging.debug(" chosenDistanceRefPool: %s" %chosenDistanceRefPool)


			# eliminate phnatom teams
			poolDistributionRef = eliminate_phantom_in_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesId"] = poolDistributionRef
# 			logging.debug(" poolDistributionRef: %s" %poolDistributionRef)

			# get coordinates for each point in the pools
			poolDistributionCoordsRef = get_coords_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesCoords"] = poolDistributionCoordsRef
# 			logging.debug(" poolDistributionCoordsRef: %s" %poolDistributionCoordsRef)

# 			# get pool details from encounters
			poolDetailsRefPlateau = create_pool_details_from_encounters_plateau(encountersRefPlateau, poolDistributionRef)
			results["scenarioRef"]["estimationDetails"] = poolDetailsRefPlateau
			logging.debug(" poolDetailsRefPlateau: \n%s" %poolDetailsRefPlateau)

			# get sum info from pool details
			sumInfoRef = get_sum_info_from_pool_details(poolDetailsRefPlateau)
			results["scenarioRef"]["estimationGenerale"] = sumInfoRef
			logging.debug(" sumInfoRef: \n%s" %sumInfoRef)
		else:
			# add boolean to results
			results["refExiste"] = 0

		logging.debug("")
		logging.debug(" ####################### RESULT OPTIMAL WITHOUT CONSTRAINT #############################################")

		# optimize distance pool only if pool numer is more than 1
		if poolNbr > 1:
			# optimal scenario without constraint
			for iterLaunch in range(config.INPUT.IterLaunchPlateau):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)#
					else:
	 					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
				else:
	 				P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_OptimalWithoutConstraint, D_Mat, iter, teamNbr)#
# 
			chosenDistance_OptimalWithoutConstraint = calculate_V_value(P_Mat_OptimalWithoutConstraint, D_Mat)
			logging.debug(" chosenDistance_OptimalWithoutConstraint: %s" %chosenDistance_OptimalWithoutConstraint)
	
			# get pool distribution
			poolDistribution_OptimalWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
# 			logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %poolDistribution_OptimalWithoutConstraint)
	 		

		# optimize distance pool only if pool numer is 1
		elif poolNbr == 1:
			poolDistribution_OptimalWithoutConstraint = {1: sorted(teams)}
			logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %poolDistribution_OptimalWithoutConstraint)

		# eliminate phnatom teams
		poolDistribution_OptimalWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesId"] = poolDistribution_OptimalWithoutConstraint
		logging.debug(" poolDistribution_OptimalWithoutConstraint: %s" %poolDistribution_OptimalWithoutConstraint)

		# get coordinates for each point in the pools
		poolDistributionCoords_OptimalWithoutConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithoutConstraint

		# optimize distance for each pool
		encounters_OptimalWithoutConstraint_Plateau = create_encounters_from_pool_distribution_plateau(poolDistribution_OptimalWithoutConstraint, welcomeConstraintExistMatchPlateau)
		results["scenarioOptimalSansContrainte"]["rencontreDetails"] = encounters_OptimalWithoutConstraint_Plateau

		# get pool details from encounters
		poolDetails_OptimalWithoutConstraint_Plateau = create_pool_details_from_encounters_plateau(encounters_OptimalWithoutConstraint_Plateau, poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationDetails"] = poolDetails_OptimalWithoutConstraint_Plateau
# 		logging.debug(" poolDetailsRefPlateau: \n%s" %poolDetailsRefPlateau)

		# get sum info from pool details
		sumInfo_OptimalWithoutConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithoutConstraint_Plateau)
		results["scenarioOptimalSansContrainte"]["estimationGenerale"] = sumInfo_OptimalWithoutConstraint
		logging.debug(" sumInfo_OptimalWithoutConstraint: \n%s" %sumInfo_OptimalWithoutConstraint)

		logging.debug("")
		logging.debug(" ####################### RESULT EQUITABLE WITHOUT CONSTRAINT ############################################")
	
		# optimize distance pool only if pool numer is more than 1
		if poolNbr > 1:
			# optimal scenario without constraint
			for iterLaunch in range(config.INPUT.IterLaunchPlateau):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)#
					else:
	 					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
				else:
	 				P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_EquitableWithoutConstraint, D_Mat, iter, teamNbr)#
# 
			chosenDistance_EquitableWithoutConstraint = calculate_V_value(P_Mat_EquitableWithoutConstraint, D_Mat)
			logging.debug(" chosenDistance_EquitableWithoutConstraint: %s" %chosenDistance_EquitableWithoutConstraint)
	
			# get pool distribution
			poolDistribution_EquitableWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
			

		# optimize distance pool only if pool numer is 1
		elif poolNbr == 1:
			poolDistribution_EquitableWithoutConstraint = {1: sorted(teams)}
			logging.debug(" poolDistribution_EquitableWithoutConstraint: %s" %poolDistribution_EquitableWithoutConstraint)


		# eliminate phnatom teams
		poolDistribution_EquitableWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesId"] = poolDistribution_EquitableWithoutConstraint
		logging.debug(" poolDistribution_EquitableWithoutConstraint: %s" %poolDistribution_EquitableWithoutConstraint)


		# get coordinates for each point in the pools
		poolDistributionCoords_EquitableWithoutConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithoutConstraint


		# optimize distance for each pool
		encounters_EquitableWithoutConstraint_Plateau = create_encounters_from_pool_distribution_plateau(poolDistribution_EquitableWithoutConstraint, welcomeConstraintExistMatchPlateau)
		results["scenarioEquitableSansContrainte"]["rencontreDetails"] = encounters_EquitableWithoutConstraint_Plateau

		# get pool details from encounters
		poolDetails_EquitableWithoutConstraint_Plateau = create_pool_details_from_encounters_plateau(encounters_EquitableWithoutConstraint_Plateau, poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationDetails"] = poolDetails_EquitableWithoutConstraint_Plateau
# 		logging.debug(" poolDetailsRefPlateau: \n%s" %poolDetailsRefPlateau)

		# get sum info from pool details
		sumInfo_EquitableWithoutConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithoutConstraint_Plateau)
		results["scenarioEquitableSansContrainte"]["estimationGenerale"] = sumInfo_EquitableWithoutConstraint
		logging.debug(" sumInfo_EquitableWithoutConstraint: \n%s" %sumInfo_EquitableWithoutConstraint)


		if statusConstraints:
			logging.debug("")
			logging.debug(" ####################### RESULT OPTIMAL WITH CONSTRAINT #############################################")
		
			# optimize distance pool only if pool numer is more than 1
			if poolNbr > 1:
				# optimal scenario with constraint
				for iterLaunch in range(config.INPUT.IterLaunchPlateau):
					logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
					# launch calculation based on ref scenario only if the params are comparable
					if iterLaunch == 0:
						# try to use ref scenario
						if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
							P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						
							if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
								P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
							else:
								# if error, launch again with P_init_matrix
								P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
								if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
									P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
								# if error, use P_init_matrix
								else:
									P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
									
						# if there is no ref scenario
						else:
							P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
								P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
					
					# for second iteration onwards
					else:
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_OptimalWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_OptimalWithConstraint = P_InitMat_withConstraint

	
				chosenDistance_OptimalWithConstraint = calculate_V_value(P_Mat_OptimalWithConstraint, D_Mat)
				logging.debug(" chosenDistance_OptimalWithConstraint: %s" %chosenDistance_OptimalWithConstraint)
		
				# get pool distribution
				poolDistribution_OptimalWithConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithConstraint, teamNbr, poolNbr, poolSize, teams)

	
			# optimize distance pool only if pool numer is 1
			elif poolNbr == 1:
				poolDistribution_OptimalWithConstraint = {1: sorted(teams)}
				logging.debug(" poolDistribution_OptimalWithConstraint: %s" %poolDistribution_OptimalWithConstraint)
	
			# eliminate phnatom teams
			poolDistribution_OptimalWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesId"] = poolDistribution_OptimalWithConstraint
			logging.debug(" poolDistribution_OptimalWithConstraint: %s" %poolDistribution_OptimalWithConstraint)
	
			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithConstraint
	
			# optimize distance for each pool
			encounters_OptimalWithConstraint_Plateau = create_encounters_from_pool_distribution_plateau(poolDistribution_OptimalWithConstraint, welcomeConstraintExistMatchPlateau)
			results["scenarioOptimalAvecContrainte"]["rencontreDetails"] = encounters_OptimalWithConstraint_Plateau
	
			# get pool details from encounters
			poolDetails_OptimalWithConstraint_Plateau = create_pool_details_from_encounters_plateau(encounters_OptimalWithConstraint_Plateau, poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationDetails"] = poolDetails_OptimalWithConstraint_Plateau
	# 		logging.debug(" poolDetailsRefPlateau: \n%s" %poolDetailsRefPlateau)
	
			# get sum info from pool details
			sumInfo_OptimalWithConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithConstraint_Plateau)
			results["scenarioOptimalAvecContrainte"]["estimationGenerale"] = sumInfo_OptimalWithConstraint
			logging.debug(" sumInfo_OptimalWithConstraint: \n%s" %sumInfo_OptimalWithConstraint)

			logging.debug("")
			logging.debug(" ######################### RESULT EQUITABLE WITH CONSTRAINT ############################################")
	
			# optimize distance pool only if pool numer is more than 1
			if poolNbr > 1:
				# equitable scenario with constraint
				for iterLaunch in range(config.INPUT.IterLaunchPlateau):
					logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)


					# launch calculation based on ref scenario only if the params are comparable
					if iterLaunch == 0:
						if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
							# try to use ref scenario
							P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						
							if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
								P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
							else:
								# if error, launch again with P_init_matrix
								P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
								if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
									P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
								# if error, use P_init_matrix
								else:
									P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
						
						
						# if there is no ref scenario
						else:
							P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
								P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
					
					
					# for second iteration onwards
					else:
						P_Mat_EquitableWithConstraint = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_EquitableWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_EquitableWithConstraint = P_InitMat_withConstraint


				chosenDistance_EquitableWithConstraint = calculate_V_value(P_Mat_EquitableWithConstraint, D_Mat)
				logging.debug(" chosenDistance_EquitableWithConstraint: %s" %chosenDistance_EquitableWithConstraint)
		
				# get pool distribution
				poolDistribution_EquitableWithConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithConstraint, teamNbr, poolNbr, poolSize, teams)

	
			# optimize distance pool only if pool numer is 1
			elif poolNbr == 1:
				poolDistribution_EquitableWithConstraint = {1: sorted(teams)}
				logging.debug(" poolDistribution_EquitableWithConstraint: %s" %poolDistribution_EquitableWithConstraint)
	
			# eliminate phnatom teams
			poolDistribution_EquitableWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesId"] = poolDistribution_EquitableWithConstraint
			logging.debug(" poolDistribution_EquitableWithConstraint: %s" %poolDistribution_EquitableWithConstraint)
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithConstraint
	
			# optimize distance for each pool
			encounters_EquitableWithConstraint_Plateau = create_encounters_from_pool_distribution_plateau(poolDistribution_EquitableWithConstraint, welcomeConstraintExistMatchPlateau)
			results["scenarioEquitableAvecContrainte"]["rencontreDetails"] = encounters_EquitableWithConstraint_Plateau
	
			# get pool details from encounters
			poolDetails_EquitableWithConstraint_Plateau = create_pool_details_from_encounters_plateau(encounters_EquitableWithConstraint_Plateau, poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationDetails"] = poolDetails_EquitableWithConstraint_Plateau
	# 		logging.debug(" poolDetailsRefPlateau: \n%s" %poolDetailsRefPlateau)
	
			# get sum info from pool details
			sumInfo_EquitableWithConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithConstraint_Plateau)
			results["scenarioEquitableAvecContrainte"]["estimationGenerale"] = sumInfo_EquitableWithConstraint
			logging.debug(" sumInfo_EquitableWithConstraint: \n%s" %sumInfo_EquitableWithConstraint)
	

		return results

	except Exception as e:
		show_exception_traceback()


"""
Main callback function which executes PyTreeRank ALgorithm
"""
def callback(ch, method, properties, body):
	try:
		beginTime = datetime.datetime.now()
		logging.debug("\n")
		logging.debug("starting current time : %s" %beginTime.strftime('%Y-%m-%d %H:%M:%S'))

		body = body.decode('utf-8')
		# get report id from RabbitMQ
		reportId = str(body)
		print("reportId: %s" %reportId)

		# update job status to 1 (running)
		update_job_status(reportId, 1)
		
# 		logging.debug("####################################### TEST INSERT PARAMS TO DB ##############################################")
# 		test_insert_params_to_db()
		
		logging.debug("####################################### READ PARAMS FROM USER ##############################################")
		# get params from DB
		sql = "select id_groupe, type_action, params from parametres where id=%s"%reportId
		logging.debug("sql: %s" %sql)
		groupId, launchType, params = db.fetchone_multi(sql)
		
		# parse json
		params = json.loads(params)
		logging.debug("groupId: %s" %groupId)
		logging.debug("launchType: %s" %launchType)
		logging.debug("params: %s" %params)

		poolNbr = params["nbrPoule"]
		logging.debug("poolNbr: %s" %poolNbr)

		# get constraint variation team number per pool
		if "varEquipeParPoule" in params:
			varTeamNbrPerPool = int(params["varEquipeParPoule"])
		else:
			varTeamNbrPerPool = 0

		# get team transfer params per pool
		if "changeAffectEquipes" in params:
			teamTransfers = params["changeAffectEquipes"]
		else:
			teamTransfers = {}
		logging.debug("teamTransfers: %s" %teamTransfers)
		

		iterConstraint = config.INPUT.IterConstraint
		logging.debug("iterConstraint: %s" %iterConstraint)

		# flag to indicate if there are phantom teams (used if the pool size is a float instead of an int)
		flagPhantom = 0
		
		# get welcome constraint for match plateau
		if "contrainteAccueilPlateauExiste" in params:
			welcomeConstraintExistMatchPlateau = params["contrainteAccueilPlateauExiste"]
		else:
			welcomeConstraintExistMatchPlateau = 0

		logging.debug("########################################### READ DATA FROM DB ##############################################")
		# get user id 
		sql = "select id_utilisateur from groupe where id=%s"%groupId
# 		logging.debug("sql: %s" %sql)

		userId = db.fetchone(sql)
		logging.debug("userId: %s" %userId)

		# get entites from DB
		sql = "select equipes from groupe where id=%d" %groupId
# 		logging.debug("sql: %s" %sql)

		# convert list of strings to list of ints
		teams = sorted(list(map(int, db.fetchone(sql).split(","))))
		logging.debug("teams: %s" %teams)
		teamNbr = len(teams)
		logging.debug("teamNbr: %s" %teamNbr)
		
		# check if teams have ref scenario or not
		withRef = check_existence_ref_scenario(teams)
		logging.debug("withRef: %s" %withRef)
		
		# check team number and pool number for match plateau
		if launchType == "plateau":
			control_params_match_plateau(userId, teamNbr, poolNbr, reportId)
		
		
		# update teams and other params by including phantom teams
		teamsWithPhantom = list(teams)
		teamNbrWithPhantom = len(teamsWithPhantom)
		phantomTeamNbr = 0


		# get prohibition constraints
		returnProhibitionConstraints = get_prohibition_constraints(params['interdictions'])
		if returnProhibitionConstraints["status"] == "yes":
			statusProhibitionConstraints = True
			prohibitionConstraints = returnProhibitionConstraints["data"]
		else:
			statusProhibitionConstraints = False
			prohibitionConstraints = []
		logging.debug("prohibitionConstraints: %s" %prohibitionConstraints)
		

		# get type distribution constraints
		returnTypeDistributionConstraints = get_type_distribution_constraints(params['repartitionHomogene'])
		if returnTypeDistributionConstraints["status"] == "yes":
			statusTypeDistributionConstraints = True
			typeDistributionConstraints = returnTypeDistributionConstraints["data"]
		else:
			statusTypeDistributionConstraints = False
			typeDistributionConstraints = {}
		logging.debug("typeDistributionConstraints: %s" %typeDistributionConstraints)
 
		# get status for constraints existence
		statusConstraints = statusProhibitionConstraints or statusTypeDistributionConstraints
		logging.debug("statusConstraints: %s" %statusConstraints)

		logging.debug("########################################### CALCULATE POOL SIZE #############################################")
		# Manage case where there is not enough teams to make even distribution of pools
		if teamNbr%poolNbr == 0:
			poolSize = int(teamNbr/poolNbr)
		else:
			flagPhantom = 1
			poolSize = 	int(teamNbr/poolNbr)+1
			teamNbrWithPhantom = poolNbr * poolSize
			phantomTeamNbr = teamNbrWithPhantom - teamNbr
			
			# add random phantom number
			for i in range(phantomTeamNbr):
				while True:
					phantomMemberId = -random.randint(1,1000)
					if phantomMemberId not in teamsWithPhantom:
						teamsWithPhantom.append(phantomMemberId)
						break

		logging.debug("poolSize: %s" %poolSize)
		logging.debug("flagPhantom: %s" %flagPhantom)
		logging.debug("teamNbrWithPhantom: %s" %teamNbrWithPhantom)
		logging.debug("phantomTeamNbr: %s" %phantomTeamNbr)
		logging.debug("teamsWithPhantom: %s" %teamsWithPhantom)

		logging.debug("####################################### CREATE DISTANCE MATRIX ##############################################")
		D_Mat = create_distance_matrix_from_db(teams, reportId, userId)

		# modify the distance matrix if there are phantom members (add zeros columns and rows) 
		if flagPhantom == 1:
			D_Mat = create_phantom_distance_matrix(D_Mat, teamNbr, poolNbr, poolSize)
			
		logging.debug("D_Mat.shape: %s" %(D_Mat.shape,))

		logging.debug("####################################### CREATE INITIALISATION MATRIX ########################################")
		P_InitMat_withoutConstraint = create_init_matrix_without_constraint(teamNbrWithPhantom, poolNbr, poolSize)
		logging.debug("P_InitMat_withoutConstraint.shape: %s" %(P_InitMat_withoutConstraint.shape,))

		# get P_Init Mat for one way
		P_InitMat_oneWaywithoutConstraint = np.triu(P_InitMat_withoutConstraint)
		logging.debug("P_InitMat_oneWaywithoutConstraint.shape: %s" %(P_InitMat_oneWaywithoutConstraint.shape,))

		# create init matrix with constraint if there is any constraint
		if statusConstraints:
			statusCreateInitMatrix = create_init_matrix_with_constraint(teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, iterConstraint, prohibitionConstraints, typeDistributionConstraints)
# 
			if statusCreateInitMatrix["success"]:
				P_InitMat_withConstraint = statusCreateInitMatrix["data"]
				logging.debug("P_InitMat_withConstraint.shape: %s" %(P_InitMat_withConstraint.shape,))
				P_InitMat_oneWayWithConstraint = np.triu(P_InitMat_withConstraint)
				logging.debug("P_InitMat_oneWayWithConstraint.shape: %s" %(P_InitMat_oneWayWithConstraint.shape,))
				
			else:
				
				# try to create init matrix manually
				statusCreateInitMatrixManual = create_init_matrix_with_constraint_manual(teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, iterConstraint, prohibitionConstraints, typeDistributionConstraints)
				
				if statusCreateInitMatrixManual["success"]:
					P_InitMat_withConstraint = statusCreateInitMatrixManual["data"]
					logging.debug("P_InitMat_withConstraint.shape: %s" %(P_InitMat_withConstraint.shape,))
					P_InitMat_oneWayWithConstraint = np.triu(P_InitMat_withConstraint)
					logging.debug("P_InitMat_oneWayWithConstraint.shape: %s" %(P_InitMat_oneWayWithConstraint.shape,))
				else:
					logging.debug("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!")
					logging.debug("Failure to create P Init Matrix which fulfills all constraints")
					send_email_to_user_failure(userId, reportId)
					logging.debug("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!")
		else:
			P_InitMat_withConstraint = None
			P_InitMat_oneWayWithConstraint = None

		logging.debug("####################################### COMPARE DISTANCES TWO WAY AND ONE WAY ###############################")
		distanceInitRoundTrip = calculate_V_value(P_InitMat_withoutConstraint, D_Mat)
		logging.debug("distanceInitRoundTrip: %s" %(distanceInitRoundTrip,))

		logging.debug("############################################# OPTIMIZE POOL #################################################")
		### Pre treatment
		if launchType == "allerRetour" and varTeamNbrPerPool == 0 and not teamTransfers:
			results = optimize_pool_round_trip_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom)
		elif launchType == "allerSimple" and varTeamNbrPerPool == 0 and not teamTransfers:
			results = optimize_pool_one_way_match(P_InitMat_oneWaywithoutConstraint, P_InitMat_oneWayWithConstraint, D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom)
		elif launchType == "plateau" and varTeamNbrPerPool == 0 and not teamTransfers:
			results = optimize_pool_plateau_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom, welcomeConstraintExistMatchPlateau)

		### check values for params teamTransfer and varTeamNbrPerPool
		check_request_validity_post_treatment(teamTransfers, varTeamNbrPerPool, userId, reportId)

		### Post treatment variation of team number
		if varTeamNbrPerPool > 0 and ( launchType in  ["allerRetour", "allerSimple"] and not teamTransfers):
			logging.debug("############################################# POST TREATMENT VARIATION OF TEAM NUMBER #########################################")
			# get old result id 
			oldResultId = params["idAncienResultat"]
			logging.debug("oldResultId : %s" %oldResultId)
			
			sql = "select details_calcul from resultats where id=%s"%oldResultId
			calculatedResult = json.loads(db.fetchone(sql))
# 			logging.debug("calculatedResult : %s" %calculatedResult)
			
			# check whether it is a final result (the variation of team members per pool has already been performed)
			check_final_result(calculatedResult, userId, reportId)

			# check given params if they are the same or not as the stocked params
			check_given_params_post_treatment(calculatedResult, launchType, poolNbr, prohibitionConstraints, typeDistributionConstraints, userId, reportId)

			results = optimize_pool_post_treatment_var_team_nbr(D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, oldResultId, userId, varTeamNbrPerPool, flagPhantom, calculatedResult, P_InitMat_withConstraint, P_InitMat_oneWayWithConstraint)
# 			logging.debug("results : \n%s" %json.dumps(results))

		### Post treatment team transfers between pools
		if varTeamNbrPerPool == 0 and ( launchType in  ["allerRetour", "allerSimple"] and  teamTransfers):
			logging.debug("############################################# POST TREATMENT TEAM TRANSFERS #########################################")

			# get old result id 
			oldResultId = params["idAncienResultat"]
			logging.debug("oldResultId : %s" %oldResultId)
			
			sql = "select details_calcul from resultats where id=%s"%oldResultId
			calculatedResult = json.loads(db.fetchone(sql))
# 			logging.debug("calculatedResult : %s" %calculatedResult)

			# check whether it is a final result (the variation of team members per pool has already been performed)
			check_final_result(calculatedResult, userId, reportId)

			# check given params if they are the same or not as the stocked params
			check_given_params_post_treatment(calculatedResult, launchType, poolNbr, prohibitionConstraints, typeDistributionConstraints, userId, reportId)

			results = optimize_pool_post_treatment_team_transfers(D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, oldResultId, userId, teamTransfers, flagPhantom, calculatedResult)
# 			logging.debug("results : \n%s" %json.dumps(results))


		if varTeamNbrPerPool == 0 and not teamTransfers:
			logging.debug("############################################# INSERT RESULT INTO DB #########################################")
			resultId = save_result_to_db(launchType, reportId, groupId, results)
			logging.debug("resultId : %s" %resultId)
		else:
			logging.debug("############################################# INSERT RESULT INTO DB #########################################")
			resultId = save_result_to_db_post_treatment(launchType, reportId, groupId, results)
			logging.debug("resultId : %s" %resultId)

		logging.debug("############################################# SEND EMAIL ####################################################")
		send_email_to_user(userId, resultId)
		logging.debug("################################################## FINISHED #################################################")

		# update job status to 2 (finished)
		update_job_status(reportId, 2)

		endTime = datetime.datetime.now()
		logging.debug("finishing current time : %s" %endTime.strftime('%Y-%m-%d %H:%M:%S'))
		processingTime = endTime - beginTime
		processingTimeSeconds = processingTime.seconds
		logging.debug("processing time : %s seconds" %processingTimeSeconds)

		# insert calculation time to DB
		insert_calculation_time_to_db(userId, beginTime, endTime, processingTimeSeconds)


	except Exception as e:
		show_exception_traceback()
	finally:
		ch.basic_ack(delivery_tag = method.delivery_tag)
		gc.collect()
		db.disconnect()
		sys.exit()

def on_connected(connection):
	print("timed_receive: Connected to RabbitMQ")

	try:
		connection.channel(on_channel_open)
	except Exception as e:
		show_exception_traceback()

def on_channel_open(channel_):
	global channel
	channel = channel_
	print("timed_receive: Received our Channel")

	try:
		channel.basic_qos(prefetch_count=1)
		
		channel.basic_consume(callback, queue=config.MQ.Queue, no_ack=True)
		
	except Exception as e:
		show_exception_traceback()



"""
Pika Asynchronous consumer
"""
class AsyncConsumer(object):
	"""This is an example consumer that will handle unexpected interactions
    with RabbitMQ such as channel and connection closures.

    If RabbitMQ closes the connection, it will reopen it. You should
    look at the output, as there are limited reasons why the connection may
    be closed, which usually are tied to permission related issues or
    socket timeouts.

    If the channel is closed, it will indicate a problem with one of the
    commands that were issued and that should surface in the output as well.

    """
	EXCHANGE = config.MQ.Exchange
	EXCHANGE_TYPE = 'direct'
	QUEUE = config.MQ.Queue

	def __init__(self, params):
		"""Create a new instance of the consumer class, passing in the AMQP
		URL used to connect to RabbitMQ.

		:param str amqp_url: The AMQP url to connect with

		"""
		self._connection = None
		self._channel = None
		self._closing = False
		self._consumer_tag = None
		self._params = params

	def connect(self):
		"""This method connects to RabbitMQ, returning the connection handle.
		When the connection is established, the on_connection_open method
		will be invoked by pika.

		:rtype: pika.SelectConnection

		"""
		print("Trying to connect")
		return pika.SelectConnection(self._params,
									 self.on_connection_open,
									 stop_ioloop_on_close=False)

	def on_connection_open(self, unused_connection):
		"""This method is called by pika once the connection to RabbitMQ has
		been established. It passes the handle to the connection object in
		case we need it, but in this case, we'll just mark it unused.

		:type unused_connection: pika.SelectConnection

		"""
		print('Connection opened')
		self.add_on_connection_close_callback()
		self.open_channel()

	def add_on_connection_close_callback(self):
		"""This method adds an on close callback that will be invoked by pika
		when RabbitMQ closes the connection to the publisher unexpectedly.

		"""
		print('Adding connection close callback')
		self._connection.add_on_close_callback(self.on_connection_closed)

	def on_connection_closed(self, connection, reply_code, reply_text):
		"""This method is invoked by pika when the connection to RabbitMQ is
		closed unexpectedly. Since it is unexpected, we will reconnect to
		RabbitMQ if it disconnects.

		:param pika.connection.Connection connection: The closed connection obj
		:param int reply_code: The server provided reply_code if given
		:param str reply_text: The server provided reply_text if given

		"""
		self._channel = None
		if self._closing:
			self._connection.ioloop.stop()
		else:
			print('Connection closed, reopening in 5 seconds: (%s) %s' %(reply_code, reply_text))
			self._connection.add_timeout(5, self.reconnect)

	def reconnect(self):
		"""Will be invoked by the IOLoop timer if the connection is
		closed. See the on_connection_closed method.

		"""
		# This is the old connection IOLoop instance, stop its ioloop
		self._connection.ioloop.stop()

		if not self._closing:

			# Create a new connection
			self._connection = self.connect()

			# There is now a new connection, needs a new ioloop to run
			self._connection.ioloop.start()

	def open_channel(self):
		"""Open a new channel with RabbitMQ by issuing the Channel.Open RPC
		command. When RabbitMQ responds that the channel is open, the
		on_channel_open callback will be invoked by pika.

		"""
		print('Creating a new channel')
		self._connection.channel(on_open_callback=self.on_channel_open)

	def on_channel_open(self, channel):
		"""This method is invoked by pika when the channel has been opened.
		The channel object is passed in so we can make use of it.

		Since the channel is now open, we'll declare the exchange to use.

		:param pika.channel.Channel channel: The channel object

		"""
		print('Channel opened')
		self._channel = channel
		self.add_on_channel_close_callback()

		self._channel.basic_qos(prefetch_count=1)

		self.setup_exchange(config.MQ.Exchange)		
# 		self.setup_queue(config.MQ.Queue)
# 		self._channel.basic_consume(callback, queue=config.MQ.Queue, no_ack=False)


	def add_on_channel_close_callback(self):
		"""This method tells pika to call the on_channel_closed method if
		RabbitMQ unexpectedly closes the channel.

		"""
		print('Adding channel close callback')
		self._channel.add_on_close_callback(self.on_channel_closed)

	def on_channel_closed(self, channel, reply_code, reply_text):
		"""Invoked by pika when RabbitMQ unexpectedly closes the channel.
		Channels are usually closed if you attempt to do something that
		violates the protocol, such as re-declare an exchange or queue with
		different parameters. In this case, we'll close the connection
		to shutdown the object.

		:param pika.channel.Channel: The closed channel
		:param int reply_code: The numeric reason the channel was closed
		:param str reply_text: The text reason the channel was closed

		"""
		print('Channel %i was closed: (%s) %s'%(channel, reply_code, reply_text))
		self._connection.close()

	def setup_exchange(self, exchange_name):
		"""Setup the exchange on RabbitMQ by invoking the Exchange.Declare RPC
		command. When it is complete, the on_exchange_declareok method will
		be invoked by pika.

		:param str|unicode exchange_name: The name of the exchange to declare

		"""
		print('Declaring exchange %s' %exchange_name)
		self._channel.exchange_declare(self.on_exchange_declareok, exchange_name)

	def on_exchange_declareok(self, unused_frame):
		"""Invoked by pika when RabbitMQ has finished the Exchange.Declare RPC
		command.

		:param pika.Frame.Method unused_frame: Exchange.DeclareOk response frame

		"""
		print('Exchange declared')
# 		self.setup_queue(self.QUEUE)
		self.setup_queue(config.MQ.Queue)

	def setup_queue(self, queue_name):
		"""Setup the queue on RabbitMQ by invoking the Queue.Declare RPC
		command. When it is complete, the on_queue_declareok method will
		be invoked by pika.

		:param str|unicode queue_name: The name of the queue to declare.

		"""
		print('Declaring queue %s' %queue_name)
# 		self._channel.queue_declare(self.on_queue_declareok, queue_name)
		self._channel.queue_declare(self.on_queue_declareok, queue_name, True)
		
	def on_queue_declareok(self, method_frame):
		"""Method invoked by pika when the Queue.Declare RPC call made in
		setup_queue has completed. In this method we will bind the queue
		and exchange together with the routing key by issuing the Queue.Bind
		RPC command. When this command is complete, the on_bindok method will
		be invoked by pika.

		:param pika.frame.Method method_frame: The Queue.DeclareOk frame

		"""
		print('Binding %s to %s' %(self.EXCHANGE, self.QUEUE))
		self._channel.queue_bind(self.on_bindok, self.QUEUE, self.EXCHANGE)

	def on_bindok(self, unused_frame):
		"""Invoked by pika when the Queue.Bind method has completed. At this
		point we will start consuming messages by calling start_consuming
		which will invoke the needed RPC commands to start the process.

		:param pika.frame.Method unused_frame: The Queue.BindOk response frame

		"""
		print('Queue bound')
		self.start_consuming()

	def start_consuming(self):
		"""This method sets up the consumer by first calling
		add_on_cancel_callback so that the object is notified if RabbitMQ
		cancels the consumer. It then issues the Basic.Consume RPC command
		which returns the consumer tag that is used to uniquely identify the
		consumer with RabbitMQ. We keep the value to use it when we want to
		cancel consuming. The on_message method is passed in as a callback pika
		will invoke when a message is fully received.

		"""
		print('Issuing consumer related RPC commands')
		self.add_on_cancel_callback()
		self._consumer_tag = self._channel.basic_consume(self.on_message,
														 self.QUEUE)

	def add_on_cancel_callback(self):
		"""Add a callback that will be invoked if RabbitMQ cancels the consumer
		for some reason. If RabbitMQ does cancel the consumer,
		on_consumer_cancelled will be invoked by pika.

		"""
		print('Adding consumer cancellation callback')
		self._channel.add_on_cancel_callback(self.on_consumer_cancelled)

	def on_consumer_cancelled(self, method_frame):
		"""Invoked by pika when RabbitMQ sends a Basic.Cancel for a consumer
		receiving messages.

		:param pika.frame.Method method_frame: The Basic.Cancel frame

		"""
		print('Consumer was cancelled remotely, shutting down: %r' %method_frame)
		if self._channel:
			self._channel.close()

	def on_message(self, unused_channel, basic_deliver, properties, body):
		"""Invoked by pika when a message is delivered from RabbitMQ. The
		channel is passed for your convenience. The basic_deliver object that
		is passed in carries the exchange, routing key, delivery tag and
		a redelivered flag for the message. The properties passed in is an
		instance of BasicProperties with the message properties and the body
		is the message that was sent.

		:param pika.channel.Channel unused_channel: The channel object
		:param pika.Spec.Basic.Deliver: basic_deliver method
		:param pika.Spec.BasicProperties: properties
		:param str|unicode body: The message body

		"""
		print('Received message # %s from %s: %s' %(basic_deliver.delivery_tag, properties.app_id, body))
		self.acknowledge_message(basic_deliver.delivery_tag)

	def acknowledge_message(self, delivery_tag):
		"""Acknowledge the message delivery from RabbitMQ by sending a
		Basic.Ack RPC method for the delivery tag.

		:param int delivery_tag: The delivery tag from the Basic.Deliver frame

		"""
		print('Acknowledging message %s' %delivery_tag)
		self._channel.basic_ack(delivery_tag)

	def stop_consuming(self):
		"""Tell RabbitMQ that you would like to stop consuming by sending the
		Basic.Cancel RPC command.

		"""
		if self._channel:
			print('Sending a Basic.Cancel RPC command to RabbitMQ')
			self._channel.basic_cancel(self.on_cancelok, self._consumer_tag)

	def on_cancelok(self, unused_frame):
		"""This method is invoked by pika when RabbitMQ acknowledges the
		cancellation of a consumer. At this point we will close the channel.
		This will invoke the on_channel_closed method once the channel has been
		closed, which will in-turn close the connection.

		:param pika.frame.Method unused_frame: The Basic.CancelOk frame

		"""
		print('RabbitMQ acknowledged the cancellation of the consumer')
		self.close_channel()

	def close_channel(self):
		"""Call to close the channel with RabbitMQ cleanly by issuing the
		Channel.Close RPC command.

		"""
		print('Closing the channel')
		self._channel.close()

	def run(self):
		"""Run the example consumer by connecting to RabbitMQ and then
		starting the IOLoop to block and allow the SelectConnection to operate.

		"""
		self._connection = self.connect()
		self._connection.ioloop.start()

	def stop(self):
		"""Cleanly shutdown the connection to RabbitMQ by stopping the consumer
		with RabbitMQ. When RabbitMQ confirms the cancellation, on_cancelok
		will be invoked by pika, which will then closing the channel and
		connection. The IOLoop is started again because this method is invoked
		when CTRL-C is pressed raising a KeyboardInterrupt exception. This
		exception stops the IOLoop which needs to be running for pika to
		communicate with RabbitMQ. All of the commands issued prior to starting
		the IOLoop will be buffered but not processed.

		"""
		print('Stopping')
		self._closing = True
		self.stop_consuming()
		self._connection.ioloop.start()
		print('Stopped')

	def close_connection(self):
		"""This method closes the connection to RabbitMQ."""
		print('Closing connection')
		self._connection.close()




"""
Main function
"""
def main():
	global config, consumer

	try:
		# parse cli arguments
		args = parse_cli_args()
	
		# get config.py location entered by user
		config_loc = args.config_loc
		print ("config_loc: %s" %config_loc)
	
		# import config module using absolute path	#  
		config = absImport(config_loc)
	
		# Init log file
		init_log_file()

		# rabbitmq connection
		credentials = pika.PlainCredentials(config.MQ.User, config.MQ.Password)
		parameters = pika.ConnectionParameters(host=config.MQ.Host, credentials=credentials, heartbeat_interval=0)

		# asynchronous RabbitMQ (problem with declaring queue)
# 		consumer = AsyncConsumer(parameters)
# 		consumer.run()

		# synchronous RabbitMQ
		connection = pika.BlockingConnection(parameters)
		channel = connection.channel()
		channel.exchange_declare(exchange=config.MQ.Exchange, durable=True)
		channel.queue_declare(queue=config.MQ.Queue, durable=True)
		channel.queue_bind(config.MQ.Queue, config.MQ.Exchange, routing_key=None)
		channel.basic_qos(prefetch_count=1)
		
		channel.basic_consume(callback, queue=config.MQ.Queue, no_ack=False)
		channel.start_consuming()
		print (' [*] Waiting for messages. To exit press CTRL+C')


	except KeyboardInterrupt:
		consumer.stop()
	except Exception as e:
		show_exception_traceback()
	finally:
		gc.collect()
		db.disconnect()

if __name__ == "__main__":
	main()
