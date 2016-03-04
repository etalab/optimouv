import sys, os
import logging
import db
import numpy as np
import random
import time
import json
import config
import smtplib
from email.mime.text import MIMEText
import requests
import datetime
import math

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
Function to calculate V value from matrix (2D array)
"""
def calculate_V_value(input_P_mat, input_D_mat):
	try:
		outputDistance = 0
	
# 		logging.debug("  input_P_mat: \n%s" %input_P_mat)
# 		logging.debug("  input_D_mat: \n%s" %input_D_mat)

		rowSize = input_P_mat.shape[0]
		colSize = input_P_mat.shape[1]
		
		for indexRow in range(rowSize):
			for indexColumn in range(colSize):
				P_mat_value = input_P_mat[indexRow, indexColumn]
				D_mat_value = input_D_mat[indexRow, indexColumn] 
				
				if D_mat_value == None:
					D_mat_value = 0
				
				outputDistance += (P_mat_value * D_mat_value ) 
# 				logging.debug("  outputDistance: %s" %outputDistance)
		return outputDistance
	except Exception as e:
		show_exception_traceback()

"""
Function to calculate V value for equitable scenario from matrix (2D array)
"""
def calculate_V_value_equitable(input_P_mat, input_D_mat):
	try:
		distances = []
	
		rowSize = input_P_mat.shape[0]
		colSize = input_P_mat.shape[1]
		
		for indexRow in range(rowSize):
			distanceRow = 0
			for indexColumn in range(colSize):
				P_mat_value = input_P_mat[indexRow, indexColumn]
				D_mat_value = input_D_mat[indexRow, indexColumn] 
				
				if D_mat_value == None:
					D_mat_value = 0
				
				distanceRow += (P_mat_value * D_mat_value )
			distances.append(distanceRow)
		return max(distances)
	except Exception as e:
		show_exception_traceback()


"""
Function to adjust pool distribution based on variation of team number per pool
"""
def adjust_pool_attribution_based_on_pool_variation(teamPoolResult, poolNbr, poolSize, varTeamNbrPerPool):
	try:
		teamPoolResultTransformed = [] 

		# determine if pool number is even or odd
		if poolNbr % 2 == 0:
			poolNbrCategory = "even"
		else:
			poolNbrCategory = "odd"
			
		# treat differently according to the pool number category
		if poolNbrCategory == "even":
			logging.debug("poolNbrCategory: %s" %poolNbrCategory)

			# create dictionary to transform pool
			transformDict = {}
			for pool in range(1, poolNbr+1):
				# variation for odd numbered member
				if pool%2 == 1:
					transformDict[pool] = poolSize - varTeamNbrPerPool
				# variation for even numbered member
				elif pool%2 == 0:
					transformDict[pool] = poolSize + varTeamNbrPerPool
			logging.debug("transformDict: %s" %transformDict)
			
			# change members in all pools
			for pool in teamPoolResult:
				# determine the number of pool size of the current pool
				currentPoolSize = teamPoolResultTransformed.count(pool)
# 					logging.debug("currentPoolSize: %s" %currentPoolSize)

				if currentPoolSize < transformDict[pool]:
					teamPoolResultTransformed.append(pool)
				else:
					# affect to the next pool
					pool = pool +1
					teamPoolResultTransformed.append(pool)

		elif poolNbrCategory == "odd":
			logging.debug("poolNbrCategory: %s" %poolNbrCategory)

			# create dictionary to transform pool
			transformDict = {}
			for pool in range(1, poolNbr+1):
				# for last pool (no variation)
				if pool == poolNbr:
					transformDict[pool] = poolSize
				# variation for odd numbered member
				elif pool%2 == 1:
					transformDict[pool] = poolSize - varTeamNbrPerPool
				# variation for even numbered member
				elif pool%2 == 0:
					transformDict[pool] = poolSize + varTeamNbrPerPool
			logging.debug("transformDict: %s" %transformDict)
			
			# change members in all pools except the last pool
			for pool in teamPoolResult:
				if pool == poolNbr:
					teamPoolResultTransformed.append(pool)
				else:
					# determine the number of pool size of the current pool
					currentPoolSize = teamPoolResultTransformed.count(pool)
# 					logging.debug("currentPoolSize: %s" %currentPoolSize)

					if currentPoolSize < transformDict[pool]:
						teamPoolResultTransformed.append(pool)
					else:
						# affect to the next pool
						pool = pool +1
						teamPoolResultTransformed.append(pool)
						
		
		logging.debug("teamPoolResultTransformed: %s" %teamPoolResultTransformed)
# 		return teamPoolResult
		return teamPoolResultTransformed
	except Exception as e:
		show_exception_traceback()

"""
Function to create pool distribution from P Matrix
"""
# def create_pool_distribution_from_matrix(P_Mat, teamNbr, poolNbr, poolSize, teams, varTeamNbrPerPool):
def create_pool_distribution_from_matrix(P_Mat, teamNbr, poolNbr, poolSize, teams):
	try:


# 		logging.debug(" P_Mat: \n%s" %P_Mat)
# 		logging.debug(" teamNbr: \n%s" %teamNbr)
# 		logging.debug(" poolNbr: \n%s" %poolNbr)
# 		logging.debug(" teams: \n%s" %teams)

		# Dict containing the distribution of groups in the pools
		poolDistribution = {}
	
		tempPools = []
		performanceCounter = 0
		assignedTeams = []
		for indexRow, teamDepart in enumerate(teams):
			# break if tempPools has reached number of desired pools
			if len(tempPools)  == poolNbr:
				break
			
			# continue to the next row if teamDepart is already in the list of assigned teams
			if teamDepart in assignedTeams:
				continue

			# get the row content
			rowContent = list(P_Mat[indexRow])
# 			logging.debug("")
# 			logging.debug("  indexRow: %s" %indexRow)
# 			logging.debug("  rowContent: %s" %rowContent)

			# calculate the pool size of the row
			poolSizeRow = rowContent.count(1.0) + 1
# 			logging.debug("  poolSizeRow: %s" %poolSizeRow)

			tempPool = [] # create a temporary pool (this pool has max size of poolSizeRow)
			tempPool.append(teamDepart) # add first element in the pool

			for indexCol, teamDestination in enumerate(teams):
				# continue to the next row if teamDepart is already in the list of assigned teams
				if teamDestination in assignedTeams:
					continue

				valueMat = int(P_Mat[indexRow][indexCol])
# 				logging.debug("  valueMat: %s" %valueMat)
# 				logging.debug("  teamDestination: %s" %teamDestination)
	
				performanceCounter += 1
	
				# add teamDestination to temporary pool if the pool size has not been reached and if the teamDestination is not yet in temporary pool 
# 				if ( len(tempPool) < poolSize) and (teamDestination not in tempPool) and (valueMat == 1):
				if ( len(tempPool) < poolSizeRow) and (teamDestination not in tempPool) and (valueMat == 1):
					tempPool.append(teamDestination)
					
				# if the pool size has been reached, push the tempPool to tempPools
# 				if len(tempPool) == poolSize:
				if len(tempPool) == poolSizeRow:
					tempPool = sorted(tempPool)
					if tempPool not in tempPools:
# 						logging.debug("  tempPool: %s" %tempPool)
						
						if len(tempPools) < poolNbr:
# 							logging.debug("  tempPool: %s" %tempPool)
							tempPools.append(tempPool)
							assignedTeams.extend(tempPool)
						else: 
							break
				
# 		logging.debug("teamNbr: \n%s" %teamNbr)
# 		logging.debug("poolNbr: \n%s" %poolNbr)
# 		logging.debug("poolSize: \n%s" %poolSize)
# 		logging.debug("teams: \n%s" %teams)
# 		logging.debug("tempPools: \n%s" %tempPools)


		firstPoolName = ord('A')
		# obtain group distribution per pool
		for pool in range(poolNbr):
			poolDistribution[pool+1] = tempPools[pool]
# 			poolDistribution[ chr(firstPoolName + pool) ] = tempPools[pool]
	
		# calculate efficiency of the algorithm
		efficiency = round((performanceCounter*100/teamNbr/teamNbr), 2)
	
# 		logging.debug("  performanceCounter: %s" %performanceCounter)
# 		logging.debug("  efficiency: %s %%" %(efficiency))
# 		logging.debug("  tempPools: %s" %tempPools)
# 		logging.debug("  len tempPools: %s" %len(tempPools))

		return poolDistribution

	except Exception as e:
		show_exception_traceback()

"""
Function to create pool distribution from P Matrix
"""
def create_pool_distribution_from_matrix_one_way(P_Mat, teamNbr, poolNbr, poolSize, teams, varTeamNbrPerPool):
	try:

# 		logging.debug(" ------------------------- create_pool_distribution_from_matrix_one_way ------------------------- ")
# 		logging.debug("  P_Mat: \n%s" %P_Mat)
# 		logging.debug("  teamNbr: %s" %teamNbr)
# 		logging.debug("  poolNbr: %s" %poolNbr)
# 		logging.debug("  poolSize: %s" %poolSize)
# 		logging.debug("  teams: %s" %teams)
		
		# Dict containing the distribution of groups in the pools
		poolDistribution = {}
	
		tempPools = []
		performanceCounter = 0 # counter which indicates the performance of the algorithm
		assignedTeams = [] # list of all assigned teams
		for indexRow, teamDepart in enumerate(teams):
			# break if tempPools has reached number of desired pools
			if len(tempPools)  == poolNbr:
				break
			
			# continue to the next row if teamDepart is already in the list of assigned teams
			if teamDepart in assignedTeams:
				continue

			# get the row content
			rowContent = list(P_Mat[indexRow])
# 			logging.debug("  rowContent: %s" %rowContent)

			# calculate the pool size of the row
			poolSizeRow = rowContent.count(1.0) + 1
# 			logging.debug("  poolSizeRow: %s" %poolSizeRow)

			# move to the next row if the pool size is smalller than expected
			if(poolSizeRow == poolSize):
				tempPool = [] # create a temporary pool (this pool has max size of poolSizeRow)
				tempPool.append(teamDepart) # add first element in the pool
	
				for indexCol, teamDestination in enumerate(teams):
					# continue to the next row if teamDepart is already in the list of assigned teams
					if teamDestination in assignedTeams:
						continue
	
					valueMat = int(P_Mat[indexRow][indexCol])
	# 				logging.debug("  valueMat: %s" %valueMat)
	# 				logging.debug("  teamDestination: %s" %teamDestination)
		
					performanceCounter += 1
		
					# add teamDestination to temporary pool if the pool size has not been reached and if the teamDestination is not yet in temporary pool 
	# 				if ( len(tempPool) < poolSize) and (teamDestination not in tempPool) and (valueMat == 1):
					if ( len(tempPool) < poolSizeRow) and (teamDestination not in tempPool) and (valueMat == 1):
						tempPool.append(teamDestination)
						
					# if the pool size has been reached, push the tempPool to tempPools
	# 				if len(tempPool) == poolSize:
					if len(tempPool) == poolSizeRow:
						tempPool = sorted(tempPool)
						if tempPool not in tempPools:
	# 						logging.debug("  tempPool: %s" %tempPool)
							
							if len(tempPools) < poolNbr:
	# 							logging.debug("  tempPool: %s" %tempPool)
								tempPools.append(tempPool)
								assignedTeams.extend(tempPool)
							else: 
								break
				
# 		logging.debug("teamNbr: \n%s" %teamNbr)
# 		logging.debug("poolNbr: \n%s" %poolNbr)
# 		logging.debug("poolSize: \n%s" %poolSize)
# 		logging.debug("teams: \n%s" %teams)
# 		logging.debug("tempPools: \n%s" %tempPools)

		firstPoolName = ord('A')
		# obtain group distribution per pool
		for pool in range(poolNbr):
			poolDistribution[pool+1] = tempPools[pool]
# 			poolDistribution[ chr(firstPoolName + pool) ] = tempPools[pool]
	
		# calculate efficiency of the algorithm
		efficiency = round((performanceCounter*100/teamNbr/teamNbr), 2)
	
# 		logging.debug("  performanceCounter: %s" %performanceCounter)
# 		logging.debug("  efficiency: %s %%" %(efficiency))
# 		logging.debug("  tempPools: %s" %tempPools)
# 		logging.debug("  len tempPools: %s" %len(tempPools))

		return poolDistribution

	except Exception as e:
		show_exception_traceback()


"""
Function to convert decimal to base 3
"""
def convert_decimal_to_base3 (n):
	try:
		if n == 0:
			return '0'
		nums = []
		while n:
			n, r = divmod(n, 3)
			nums.append(str(r))
		return ''.join(reversed(nums))
	except Exception as e:
		show_exception_traceback()

"""
Function to get combination from base3
"""
def get_host_combination_index_from_base3(combinationInput, base3Tmp):
	try:

		combinationOutput = {}
		for day, combinationPerDay in combinationInput.items():
			
# 			logging.debug("  day: %s" %day)
			fromIndex = (day-1)*3
# 			logging.debug("  fromIndex: %s" %fromIndex)
			toIndex = ((day-1)*3)+2
# 			logging.debug("  toIndex: %s" %toIndex)
			
			combinationPerDayInput = base3Tmp[fromIndex:toIndex+1]
# 			logging.debug("  combinationPerDayInput: %s" %combinationPerDayInput)
			
			
			combinationOutput[day] = [int(value) for value in combinationPerDayInput]
		
# 		logging.debug("  combinationOutput: %s" %combinationOutput)
		
		
		return combinationOutput
	except Exception as e:
		show_exception_traceback()

"""
Function to get host names from Plateau distribution Per pool
"""
def get_member_combination_ids_from_host_combination_index(plateauDistributionPerPool, combination):
	try:
		memberCombinationIds = {}
	
		for day, contentDay in plateauDistributionPerPool.items():
			memberCombinationIds[day] = {}
			
			for indexGroup, group in enumerate(contentDay):
# 				logging.debug("  group: %s" %group)
				hostIndex = combination[day][indexGroup]
# 				logging.debug("  hostIndex: %s" %hostIndex)
				hostId = group[hostIndex]
# 				logging.debug("  hostId: %s" %hostId)
				
				# get member ids
				memberIds = list(group)
				if hostId in memberIds:
					memberIds.remove(hostId)
				
				distanceGroup = 0
				
				for memberId in memberIds:
					sql = "select distance from trajet where depart=%s and destination=%s"%(memberId, hostId)
					distanceGroup += int(db.fetchone(sql))
				
				memberCombinationIds[day][indexGroup+1] = {"hostId": hostId, "memberIds": memberIds, "distanceGroup": distanceGroup}
			
		
		return memberCombinationIds
			
	except Exception as e:
		show_exception_traceback()

"""
Function to get distance for a certain member combination ids
"""
def get_distance_for_member_combination_ids(memberCombinationIds):
	try:
		distanceMemberCombination = 0
		
		for day, contentDay in memberCombinationIds.items():
			for group, contentGroup in contentDay.items():
				distanceMemberCombination += contentGroup["distanceGroup"]
		
		return distanceMemberCombination
	except Exception as e:
		show_exception_traceback()

"""
Function to check if all teams welcome at least one time 
@return: 0 if false
@return: 1 if true
"""
def check_welcome_constraint_match_plateau(memberCombinationIds, teams):
	try:
		statusCheckWelcomeConstraint = 0
		welcomingTeams = []
		
		for day, contentDay in memberCombinationIds.items():
			for group, contentGroup in contentDay.items():
				hostIdTmp = int(contentGroup["hostId"])
# 				logging.debug("hostIdTmp: %s" %hostIdTmp)
				if hostIdTmp not in welcomingTeams:
					welcomingTeams.append(hostIdTmp) 
		
# 		logging.debug("welcomingTeams: %s" %welcomingTeams)
# 		logging.debug("teams: %s" %teams)

		# compare list of welcoming teams with all teams
		if sorted(welcomingTeams) == teams:
			statusCheckWelcomeConstraint = 1

		return statusCheckWelcomeConstraint
	except Exception as e:
		show_exception_traceback()

"""
Function to check if each team receives max two times (three times is an error) 
@return: 0 if false
@return: 1 if true
"""
def check_max_times_host_match_plateau(memberCombinationIds):
	try:
		statusMaxTimesHost = 0


		receivingHosts = {}
		
		# get the statistics about the receiving hosts
		for day, contentDay in memberCombinationIds.items():
			for group, contentGroup in contentDay.items():
				if "hostId" in contentGroup:
					hostId = contentGroup["hostId"]
					
					if hostId not in receivingHosts:
						receivingHosts[hostId] = 1
					else:
						receivingHosts[hostId] += 1
						
						# return false if there is any member who receives more than two times
						if receivingHosts[hostId] > 2:
# 							logging.debug("memberCombinationIds: \n%s" %json.dumps(memberCombinationIds))
# 							logging.debug("receivingHosts: \n%s" %json.dumps(receivingHosts))
# 							logging.debug("")
							return statusMaxTimesHost
		
		# set status to true
		statusMaxTimesHost = 1

# 		logging.debug("memberCombinationIds: \n%s" %json.dumps(memberCombinationIds))
# 		logging.debug("receivingHosts: \n%s" %json.dumps(receivingHosts))
# 		logging.debug("")
		return statusMaxTimesHost

	except Exception as e:
		show_exception_traceback()

"""
Function to calculate distance plateau for a given 3x4 matrix (plateau distribution)
"""
def calculate_shortest_distance_plateau_from_3_4_matrix(plateauDistributionPerPool, welcomeConstraintExistMatchPlateau, teams):
	try:

		
# 		logging.debug("  plateauDistribution: %s" %plateauDistributionPerPool)

		# initialize host combination
		hostCombinationIndex = {}
		for day, contentDay in plateauDistributionPerPool.items():
			hostCombinationIndex[day] = []
			for indexGroup, group in enumerate(contentDay, start=1):
				hostCombinationIndex[day].append(0)
		
# 		logging.debug("  hostCombinationIndex: %s" %hostCombinationIndex)
		
		# calculate the total number of  host combination
		hostCombinationNbr = 1
		for day, contentDay in plateauDistributionPerPool.items():
# 			logging.debug("  day: %s" %day)
			for indexGroup, group in enumerate(contentDay, start=1):
# 				logging.debug("  indexGroup: %s group: %s" %(indexGroup, group))
				hostCombinationNbr *= len(group)

		logging.debug("  hostCombinationNbr: %s" %hostCombinationNbr)
		logging.debug("  plateauDistributionPerPool:%s"%plateauDistributionPerPool)
		logging.debug("  welcomeConstraintExistMatchPlateau:%s"%welcomeConstraintExistMatchPlateau)
		logging.debug("  teams:%s"%teams)

		# find the shortest distance
		bestHostCombinationIndex = {}
		bestMemberCombinationIds = {}
		bestDistanceMemberCombination = 0
		bestIterationNbr = 0
		for i in range(hostCombinationNbr):
			if i%100 == 0:
				logging.debug("  i: %s " %(i))

			if i == config.INPUT.IterShortestDistancePlateau:
				break
			
			# convert iteration number to base 3
			base3Tmp = str(convert_decimal_to_base3(i))
# 			logging.debug("  base3Tmp: %s" %base3Tmp)

			# complete base3 to 12 characters
			for k in range(12-len(base3Tmp)):
				base3Tmp = '0' + base3Tmp
# 			logging.debug("  base3Tmp: %s" %base3Tmp)

			# get combination of index host for each day  
			hostCombinationIndex = get_host_combination_index_from_base3(hostCombinationIndex, base3Tmp)
# 			logging.debug("  hostCombinationIndex: %s" %hostCombinationIndex)

			# get host names from combination
			memberCombinationIds = get_member_combination_ids_from_host_combination_index(plateauDistributionPerPool, hostCombinationIndex)
# 			logging.debug("  memberCombinationIds: %s" %memberCombinationIds)
			
			# if the flag of welcome constraint is checked
			if(int(welcomeConstraintExistMatchPlateau) == 1):
				# check welcome constraint for match plateau 
				
				statusCheckWelcomeConstraintMatchPlateau = check_welcome_constraint_match_plateau(memberCombinationIds, teams)
# 				logging.debug("  statusCheckWelcomeConstraintMatchPlateau: %s" %statusCheckWelcomeConstraintMatchPlateau)
				
				if statusCheckWelcomeConstraintMatchPlateau == 0:
					continue
			
			# each member can only become host at most txo times (two days)
			statusCheckMaxTimesHost = check_max_times_host_match_plateau(memberCombinationIds)
			logging.debug("  statusCheckMaxTimesHost: %s" %statusCheckMaxTimesHost)
			
			if statusCheckMaxTimesHost == 0:
				continue
			
			
			# calculate distance total for a specific member combination
			distanceMemberCombination = get_distance_for_member_combination_ids(memberCombinationIds)
# 			logging.debug("  distanceMemberCombination: %s" %distanceMemberCombination)

			# assign the value for the first time
			if bestDistanceMemberCombination == 0:
				bestDistanceMemberCombination = distanceMemberCombination
				bestHostCombinationIndex = hostCombinationIndex
				bestMemberCombinationIds = memberCombinationIds
				
			# compare with current best value
			if distanceMemberCombination < bestDistanceMemberCombination:
				bestDistanceMemberCombination = distanceMemberCombination
				bestHostCombinationIndex = hostCombinationIndex
				bestMemberCombinationIds = memberCombinationIds
				bestIterationNbr = i


		
		logging.debug("  bestIterationNbr: %s" %bestIterationNbr)
# 		logging.debug("  bestDistanceMemberCombination: %s" %bestDistanceMemberCombination)
# 		logging.debug("  bestHostCombinationIndex: %s" %bestHostCombinationIndex)
# 		logging.debug("  bestMemberCombinationIds: %s" %bestMemberCombinationIds)
		
# 		sys.exit()
		
		result = {	"bestDistance": bestDistanceMemberCombination, 
					"bestHostCombinationIndex": bestHostCombinationIndex,
					"bestMemberCombinationIds": bestMemberCombinationIds,
					}
		return result
		
	except Exception as e:
		show_exception_traceback()

"""
Function to get encounters details from member combination ids
"""
def get_encounters_details_from_member_combination_ids(memberCombinationIds):
	try:
		encountersDetailsPlateau = {}

		for day, contentDay in memberCombinationIds.items():
			encountersDetailsPlateau[day] = []
			
			for group, contentGroup in contentDay.items():
				groupTmp = {
							"hoteId": contentGroup["hostId"],
							"premierEquipeId": contentGroup["memberIds"][0],
							"deuxiemeEquipeId": contentGroup["memberIds"][1],
							"distanceGroupe": contentGroup["distanceGroup"],
							
						}
				
				# get city name and postal code of host
				sql = "select ville, nom, code_postal from entite where id=%s"%contentGroup["hostId"]
				hostCity, hostName, hostPostalCode =   db.fetchone_multi(sql)
				groupTmp["hoteVille"] = hostCity.replace("'", u"''")
				groupTmp["hoteNom"] = hostName.replace("'", u"''")
				groupTmp["hoteCodePostal"] = hostPostalCode

# 				logging.debug("  hostCity: %s" %hostCity)
# 				logging.debug("  hostName: %s" %hostName)
# 				logging.debug("  hostPostalCode: %s" %hostPostalCode)
				
				# get city name and postal code of first team
				sql = "select ville, nom, code_postal, participants from entite where id=%s"%contentGroup["memberIds"][0]
				firstTeamCity, firstTeamName, firstTeamPostalCode, firstTeamParticipantsNbr =   db.fetchone_multi(sql)
				groupTmp["premierEquipeVille"] = firstTeamCity.replace("'", u"''")
				groupTmp["premierEquipeNom"] = firstTeamName.replace("'", u"''")
				groupTmp["premierEquipeCodePostal"] = firstTeamPostalCode
				groupTmp["nbrParticipants"] = firstTeamParticipantsNbr
				

				# get city name and postal code of second team
				sql = "select ville, nom, code_postal from entite where id=%s"%contentGroup["memberIds"][1]
				secondTeamCity, secondTeamName, secondTeamPostalCode =   db.fetchone_multi(sql)
				groupTmp["deuxiemeEquipeVille"] = secondTeamCity.replace("'", u"''")
				groupTmp["deuxiemeEquipeNom"] = secondTeamName.replace("'", u"''")
				groupTmp["deuxiemeEquipeCodePostal"] = secondTeamPostalCode

				# get distance for all participants
				groupTmp["distanceGroupeTousParticipants"] = groupTmp["distanceGroupe"] * groupTmp["nbrParticipants"]

				# get travel time for the group
				travelTime = 0
				travelIds = []
				travellNames = []
				for memberId in contentGroup["memberIds"]:
					sql = "select duree from trajet where depart=%s and destination=%s"%(memberId, contentGroup["hostId"])
					travelTime += int(db.fetchone(sql))
					travelIds.append([contentGroup["hostId"], memberId])

					sql = "select ville from entite where id=%s"%memberId
					memberName = db.fetchone(sql)
					travellNames.append([hostName.replace("'", u"''"), memberName.replace("'", u"''") ] )
				groupTmp["dureeGroupe"] = travelTime
				groupTmp["deplacementsIds"] = travelIds
				groupTmp["deplacementsNoms"] = travellNames

				encountersDetailsPlateau[day].append(groupTmp)
			
		return encountersDetailsPlateau

	except Exception as e:
		show_exception_traceback()


"""
Function to create encounters from pool distribution for match plateau
"""
def create_encounters_from_pool_distribution_plateau(poolDistribution, welcomeConstraintExistMatchPlateau):
	try:
		encountersPlateau = {}

		logging.debug("")


		bestDistancePerPool = {}
		for pool, teams in poolDistribution.items():
			logging.debug("  teams: %s" %teams)
			encountersPlateau[pool] = {}

			# init vars
			bestDistancePerPool[pool] = 0
			bestMemberCombinationIds = {}
			for i in range(config.INPUT.IterPlateau):
				logging.debug(" ----------------------------------  iteration match plateau: %s ----------------------------------------" %i)

				# assign random value for each team
				teamRandomValues = [round(random.random() * 100) for i in range(len(teams))]
	# 			logging.debug("  teamRandomValues: %s" %teamRandomValues)
	
				# get the index values of the sorted random values
				indexSortedTeamRandomValues = list(range(1, len(teamRandomValues)+1))
				indexSortedTeamRandomValues = sorted( indexSortedTeamRandomValues, key=lambda k: teamRandomValues[indexSortedTeamRandomValues.index(k)] )
				logging.debug("  indexSortedTeamRandomValues: %s" %indexSortedTeamRandomValues)
	
	
				# assign teams based on their random number values according to the established matrix
				firstTeamAssigned = teams[indexSortedTeamRandomValues.index(1)]
				secondTeamAssigned = teams[indexSortedTeamRandomValues.index(2)]
				thirdTeamAssigned = teams[indexSortedTeamRandomValues.index(3)]
				fourthTeamAssigned = teams[indexSortedTeamRandomValues.index(4)]
				fifthTeamAssigned = teams[indexSortedTeamRandomValues.index(5)]
				sixthTeamAssigned = teams[indexSortedTeamRandomValues.index(6)]
				seventhTeamAssigned = teams[indexSortedTeamRandomValues.index(7)]
				eighthTeamAssigned = teams[indexSortedTeamRandomValues.index(8)]
				ninthTeamAssigned = teams[indexSortedTeamRandomValues.index(9)]
	
				# temporary plateau distribution per pool
				plateauDistributionPerPoolTmp = {	1: [ 	[ firstTeamAssigned, secondTeamAssigned, thirdTeamAssigned ], 
															[ fourthTeamAssigned, fifthTeamAssigned, sixthTeamAssigned ], 
															[ seventhTeamAssigned, eighthTeamAssigned, ninthTeamAssigned] ],
													2: [ 	[ thirdTeamAssigned, fifthTeamAssigned, eighthTeamAssigned ],
															[ firstTeamAssigned, sixthTeamAssigned, ninthTeamAssigned ], 
															[ secondTeamAssigned, fourthTeamAssigned, seventhTeamAssigned] ],
													3: [ 	[ firstTeamAssigned, fourthTeamAssigned, eighthTeamAssigned], 
															[ thirdTeamAssigned, sixthTeamAssigned, seventhTeamAssigned], 
															[ secondTeamAssigned, fifthTeamAssigned, ninthTeamAssigned] ],
													4: [ 	[ thirdTeamAssigned, fourthTeamAssigned, ninthTeamAssigned], 
															[ secondTeamAssigned, sixthTeamAssigned, eighthTeamAssigned], 
															[ firstTeamAssigned, fifthTeamAssigned, seventhTeamAssigned] ],
												}
				logging.debug("  plateauDistributionPerPoolTmp: %s" %plateauDistributionPerPoolTmp)
	
				returnShortestDistance = calculate_shortest_distance_plateau_from_3_4_matrix(plateauDistributionPerPoolTmp, welcomeConstraintExistMatchPlateau, teams)
	# 			logging.debug("  returnShortestDistance: %s" %returnShortestDistance)
				
				# for first iteration
				if i == 0:
					bestDistancePerPool[pool] = returnShortestDistance["bestDistance"]
					logging.debug("  bestDistance: %s" %bestDistancePerPool[pool])

					bestMemberCombinationIds = returnShortestDistance["bestMemberCombinationIds"]
# 					logging.debug("  bestMemberCombinationIds: %s" %bestMemberCombinationIds)	

				# for second onward iterations
				else:
					if returnShortestDistance["bestDistance"] < bestDistancePerPool[pool]:
						bestDistancePerPool[pool] = returnShortestDistance["bestDistance"]
						logging.debug("  bestDistance: %s" %bestDistancePerPool[pool])
				
						bestMemberCombinationIds = returnShortestDistance["bestMemberCombinationIds"]
# 						logging.debug("  bestMemberCombinationIds: %s" %bestMemberCombinationIds)
		

			logging.debug(" -----------------------  FINISHED ITERATION PLATEAU FOR POOL: %s  ----------------------------------------"%pool)
			logging.debug(" bestMemberCombinationIds: %s "%bestMemberCombinationIds)
		
			# get encounter details from member combination ids
			encountersDetailsPlateauPerPool = get_encounters_details_from_member_combination_ids(bestMemberCombinationIds)
# 			logging.debug(" encountersDetailsPlateauPerPool: \n%s "%json.dumps(encountersDetailsPlateauPerPool))
			
			encountersPlateau[pool] = encountersDetailsPlateauPerPool
			
		logging.debug(" ")
		logging.debug(" bestDistancePerPool: %s "%bestDistancePerPool)
# 		logging.debug("  encountersPlateau: \n%s" %json.dumps(encountersPlateau))

		return encountersPlateau 

	except Exception as e:
		show_exception_traceback()



"""
Function to create encounters from pool distribution
"""
def create_encounters_from_pool_distribution(poolDistribution):
	try:
		encounters = {}
		
		for pool, members in poolDistribution.items():
			encounters[pool] = {}
			encounterNbr = 0
			for member1 in members:
				for member2 in members:
					if member1 != member2:
						encounterNbr += 1

						# calculate distance and travel time
						sql = "select distance, duree from trajet where depart=%s and destination=%s" %(member1, member2)
# 						logging.debug("  sql: %s" %(sql))
						distance, travelTime = db.fetchone_multi(sql)

						sql = "select participants, nom, ville, code_postal from entite where id=%s" %member1
						nbrParticipants1, name1, city1, postalCode1 = db.fetchone_multi(sql)

						sql = "select participants, nom, ville, code_postal from entite where id=%s" %member2
						nbrParticipants2, name2, city2, postalCode2 = db.fetchone_multi(sql)
		
						distanceAllParticipants = int(distance) * int(nbrParticipants1)
		
						# Escape single apostrophe for name and city
						name1 = name1.replace("'", u"''")
# 						name1 = name1.replace("'", u"")
# 						logging.debug("  name1: %s" %(name1))
						name2 = name2.replace("'", u"''")
# 						name2 = name2.replace("'", u"")
# 						logging.debug("  name2: %s" %(name2))
						city1 = city1.replace("'", u"''")
# 						city1 = city1.replace("'", u"")
# 						logging.debug("  city1: %s" %(city1))
						city2 = city2.replace("'", u"''")
# 						city2 = city2.replace("'", u"")
# 						logging.debug("  city2: %s" %(city2))

						encounter = {"equipeDepartId": member1, "equipeDestinationId": member2, 
														"distance": distance, "duree": travelTime,
														"nbrParticipants": nbrParticipants1, "distanceTousParticipants": distanceAllParticipants,
														"equipeDepartNom": name1, "equipeDestinationNom": name2,
														"equipeDepartVille": city1, "equipeDestinationVille": city2,
														"equipeDepartCodePostal": postalCode1, "equipeDestinationCodePostal": postalCode2
														
														}
						encounters[pool][encounterNbr] = encounter

		return encounters

	except Exception as e:
		show_exception_traceback()


"""
Function to create encounters from pool distribution
"""
def create_encounters_from_pool_distribution_one_way(poolDistribution):
	try:
		encounters = {}
		
		for pool, members in poolDistribution.items():
			encounters[pool] = {}
			encounterNbr = 0
			encountersTmp = [] # list of possible encounter combinations
			for member1 in members:
				for member2 in members:
					firstCombination = [member1, member2]
					secondCombination = [member2, member1]

					if (member1 != member2) and (firstCombination not in encountersTmp) and (secondCombination not in encountersTmp):
						encountersTmp.append(firstCombination)
						encountersTmp.append(secondCombination)
					
						encounterNbr += 1

						# calculate distance and travel time
						sql = "select distance, duree from trajet where depart=%s and destination=%s" %(member1, member2)
# 						logging.debug("  sql: %s" %(sql))
						distance, travelTime = db.fetchone_multi(sql)

						sql = "select participants, nom, ville, code_postal from entite where id=%s" %member1
						nbrParticipants1, name1, city1, postalCode1 = db.fetchone_multi(sql)

						sql = "select participants, nom, ville, code_postal from entite where id=%s" %member2
						nbrParticipants2, name2, city2, postalCode2 = db.fetchone_multi(sql)
		
						distanceAllParticipants = int(distance) * int(nbrParticipants1)
		
						# Escape single apostrophe for name and city
						name1 = name1.replace("'", u"''")
# 						logging.debug("  name1: %s" %(name1))
						name2 = name2.replace("'", u"''")
# 						logging.debug("  name2: %s" %(name2))
						city1 = city1.replace("'", u"''")
# 						logging.debug("  city1: %s" %(city1))
						city2 = city2.replace("'", u"''")
# 						logging.debug("  city2: %s" %(city2))

						encounter = {"equipeDepartId": member1, "equipeDestinationId": member2, 
														"distance": distance, "duree": travelTime,
														"nbrParticipants": nbrParticipants1, "distanceTousParticipants": distanceAllParticipants,
														"equipeDepartNom": name1, "equipeDestinationNom": name2,
														"equipeDepartVille": city1, "equipeDestinationVille": city2,
														"equipeDepartCodePostal": postalCode1, "equipeDestinationCodePostal": postalCode2
														
														}
						encounters[pool][encounterNbr] = encounter

		return encounters

	except Exception as e:
		show_exception_traceback()

"""
Function to create pool details from encounters
"""
def create_pool_details_from_encounters(encounters, poolDistribution):
	try:
		poolDetails = {}
		
		for pool, encountersDetails in encounters.items():
# 			poolDetails[pool] = {"totalDistance": 0, "totalTravelTime": 0, "totalDistanceAllParticipants": 0, "totalParticipants": 0}
			poolDetails[pool] = {"distanceTotale": 0, "dureeTotale": 0, "distanceTotaleTousParticipants": 0, "nbrParticipantsTotal": 0}
		
			members = poolDistribution[pool]
			
			# get sum of participants for each team member
			for member in members:
				sql = "select participants from entite where id=%s" %member
				nbrParticipants = db.fetchone(sql)
# 				poolDetails[pool]["totalParticipants"] += int(resultSql)
				poolDetails[pool]["nbrParticipantsTotal"] += int(nbrParticipants)
		
			# get sum of other details
			for encounterNbr, encounterDetails in encountersDetails.items():
# 				logging.debug("  encounterDetails: %s" %(encounterDetails, ))
# 				poolDetails[pool]["totalDistance"] += encounterDetails["distance"]
				poolDetails[pool]["distanceTotale"] += encounterDetails["distance"]
# 				poolDetails[pool]["totalTravelTime"] += encounterDetails["travelTime"]
				poolDetails[pool]["dureeTotale"] += encounterDetails["duree"]
				poolDetails[pool]["distanceTotaleTousParticipants"] += encounterDetails["distanceTousParticipants"]
		
		return poolDetails
		
		
	except Exception as e:
		show_exception_traceback()

"""
Function to create pool details from encounters for match plateau
"""
def create_pool_details_from_encounters_plateau(encountersPlateau, poolDistribution):
	try:
		poolDetailsPlateau = {}
	
# 		logging.debug("  poolDistribution: %s" %(poolDistribution))
	
		for pool, contentPool in encountersPlateau.items():
			poolDetailsPlateau[pool] = {	"distanceTotale": 0,
											"dureeTotale": 0,
											"nbrParticipantsTotal": 0,
											"distanceTotaleTousParticipants": 0,
											}
			teamsIds = poolDistribution[pool]
			
			
			for teamId in teamsIds: 
				sql = "select participants from entite where id=%s"%teamId
				nbrParticipants = int(db.fetchone(sql))
				poolDetailsPlateau[pool]["nbrParticipantsTotal"] += nbrParticipants
			
			for day, contentDay in contentPool.items():
				for contentGroup in contentDay:
					poolDetailsPlateau[pool]["distanceTotale"] += contentGroup["distanceGroupe"]
					poolDetailsPlateau[pool]["dureeTotale"] += contentGroup["dureeGroupe"]
					poolDetailsPlateau[pool]["distanceTotaleTousParticipants"] += contentGroup["distanceGroupeTousParticipants"]
				

		return poolDetailsPlateau

	except Exception as e:
		show_exception_traceback()

"""
Function to get the sum of all info from pool details
"""
def get_sum_info_from_pool_details(poolDetails):
	try:
		sumInfo = {}
		
		for pool, poolContent in poolDetails.items():
			for info, infoValue in poolContent.items():
				if info in sumInfo:
					sumInfo[info] +=  infoValue
				else:
					sumInfo[info] =  infoValue
		
		return sumInfo
		
	except Exception as e:
		show_exception_traceback()



"""
Function to get indexes of prohibition constraints
"""
def getIndexesProhibitionConstraints(prohibitionConstraints, teams):
	try:
		indexesProhibitionConstraints = []


		for constraint in prohibitionConstraints:
# 			logging.debug("  prohibitionConstraints: %s" %(prohibitionConstraints))
			member1 = int(constraint[0])
			member2 = int(constraint[1])
			indexesTmp = [ teams.index(member1), teams.index(member2) ]
			indexesProhibitionConstraints.append(indexesTmp)

		return indexesProhibitionConstraints
	except Exception as e:
		show_exception_traceback()
		
		
"""
Function to get indexes of type distribution constraints
"""
def getIndexesTypeDistributionConstraints(typeDistributionConstraints, teams):
	try:
		indexesTypeDistributionConstraints = {}
		

		for type, constraint in typeDistributionConstraints.items():
			indexesTmp = []
			for member in constraint:
				indexesTmp.append(teams.index(int(member)))
			indexesTypeDistributionConstraints[type] = indexesTmp

		return indexesTypeDistributionConstraints	
	except Exception as e:
		show_exception_traceback()



"""
Function to create rules for prohibition constraints
"""
def create_rules_for_prohibition_constraints(indexesProhibitionConstraints, P_Mat):
	try:
		rulesConstraints = []

		# create list of prohibited transfer i j based on indexes of prohibition constraints
		for index in indexesProhibitionConstraints:
			member1 = index[0]
			member2 = index[1]
		
			# get current team members of member1
			membersOf1 = P_Mat[member1]
# 				logging.debug("  membersOf1: %s" %(membersOf1))
			indexesMembersOf1 = list(np.where(membersOf1 == 1)[0])
# 				logging.debug("  member2: %s" %(member2))
# 				logging.debug("  indexesMembersOf1: %s" %(indexesMembersOf1))
			
			# get current team members member2
			membersOf2 = P_Mat[member2]
# 				logging.debug("  membersOf2: %s" %(membersOf2))
			indexesMembersOf2 = list(np.where(membersOf2 == 1)[0])
# 				logging.debug("  member1: %s" %(member1))
# 				logging.debug("  indexesMembersOf2: %s" %(indexesMembersOf2))

			# create prohibition rules
			rulesMember1 = [] # member 1 with current team members of member2
			rulesMember2 = [] # member 2 with current team members of member1

			# between member1 and current team members of member2
			for indexMemberOf2 in indexesMembersOf2:
				listTemp = sorted([member1, indexMemberOf2])
				rulesMember1.append(listTemp) 
# 				logging.debug("  rulesMember1: %s" %(rulesMember1))

			# between member2 and current team members of member1
			for indexMemberOf1 in indexesMembersOf1:
				listTemp = sorted([member2, indexMemberOf1])
				rulesMember2.append(listTemp) 
# 				logging.debug("  rulesMember2: %s" %(rulesMember2))

			# concatenate the two rules
			rulesConstraint = rulesMember1 + rulesMember2
# 				logging.debug("  rulesConstraint: %s" %(rulesConstraint))

			rulesConstraints += rulesConstraint
		
		return rulesConstraints
		
	except Exception as e:
		show_exception_traceback()


"""
Function to get P Matrix for Round Trip Match Optimal Scenario Without Constraint
"""
def get_p_matrix_for_round_trip_match_optimal_without_constraint(P_InitMat, D_Mat, iter,  teamNbr):
	try:		
		# calculate initial distance
		initDistance = calculate_V_value(P_InitMat, D_Mat)
		logging.debug("  initDistance: %s" %initDistance)
	
		# calculate initial T_Value
		T_Value = 0.1 * initDistance
		logging.debug("  T_Value initial: %s" %T_Value)

		for nbIter in range(iter):
			logging.debug("  ----------------------------------------------------------------------------------------------------")
			logging.debug("  nbIter: %s" %nbIter)
			logging.debug("  ----------------------------------------------------------------------------------------------------")
	
			# Function T_value
			T_Value *= 0.99
			logging.debug("  T_Value current: %s" %T_Value)
	
			### get index to change row and column
			while True:
				transIndex = random.sample(range(teamNbr), 2)
			
				i = transIndex[0]
				j = transIndex[1]
				P_ij = P_InitMat[i][j]
			
				if i <= j and int(P_ij) == 0:
					logging.debug("  i: %s, j: %s" %(i, j))
					break
	# 			
			P_TransMat = np.copy(P_InitMat)
	
			# change two columns according to transIndex
			P_TransMat[transIndex,:] = P_TransMat[list(reversed(transIndex)),:]
			# change two rows according to transIndex
			P_TransMat[:,transIndex] = P_TransMat[:,list(reversed(transIndex))]
	# 		logging.debug("  P_InitMat: \n%s" %P_InitMat)
	# 		logging.debug("  P_TransMat: \n%s" %P_TransMat)
	# 
			V_oriValue = calculate_V_value(P_InitMat, D_Mat)
			logging.debug("  V_oriValue: %s" %V_oriValue)
	
			V_transValue = calculate_V_value(P_TransMat, D_Mat)
			logging.debug("  V_transValue: %s" %V_transValue)
	
			deltaV = V_oriValue - V_transValue
			logging.debug("  deltaV: %s" %deltaV)
			
			# temperature function 
			# reinitialize temperature in the middle of loop
			if nbIter == int(iter/2):
				T_Value = 0.1 * initDistance
			# reinitilize temperature if deltaV is at least 5% of V_oriValue
			if deltaV >= 0.05 * V_oriValue:
				T_Value = 0.1 * initDistance
	
			if deltaV <= 0:
				pass
			else:
				randValue = random.random()
	# 			logging.debug("randValue: %s" %randValue)
	
				expValue = math.exp(-deltaV/T_Value)
	# 			logging.debug("expValue: %s" %expValue)
	
				if randValue <= expValue:
					pass
				else:
					P_InitMat = P_TransMat

		logging.debug("")

		return P_InitMat
		
	except Exception as e:
		show_exception_traceback()


"""
Function to get P Matrix for Round Trip and One Way Match Optimal Scenario With Constraints
"""
def get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId):
	try:		
		# calculate initial distance
		initDistance = calculate_V_value(P_InitMat, D_Mat)
		logging.debug("  initDistance: %s" %initDistance)
	
		# calculate initial T_Value
		T_Value = 0.1 * initDistance
		logging.debug("  T_Value initial: %s" %T_Value)
		
		logging.debug("  iterConstraint: %s" %iterConstraint)
		logging.debug("	 prohibitionConstraints: \n%s" %json.dumps(prohibitionConstraints))
		logging.debug("  typeDistributionConstraints: \n%s" %json.dumps(typeDistributionConstraints))

		for nbIter in range(iter):
			logging.debug("  ----------------------------------------------------------------------------------------------------")
			logging.debug("  nbIter: %s" %nbIter)
			logging.debug("  ----------------------------------------------------------------------------------------------------")
	
			# Function T_value
			T_Value *= 0.99
			logging.debug("  T_Value current: %s" %T_Value)
	
			poolDistributionInit = create_pool_distribution_from_matrix(P_InitMat, teamNbr, poolNbr, poolSize, teams)
			logging.debug("  poolDistributionInit: \n%s" %poolDistributionInit)


			### get index to change row and column
			while True:
				
				if iterConstraint == 0:
					logging.debug("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!")
					logging.debug("Failure to create interchange rows and  columns (i, j) which fulfills all constraints")
					logging.debug("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!")
# 					# update status job failure
					update_job_status(reportId, -1)
# 					send_email_to_user_failure(userId)
					send_email_to_user_failure(userId, reportId)
					sys.exit()
				iterConstraint -= 1
				
				transIndex = random.sample(range(teamNbr), 2)
			
				i = transIndex[0]
				j = transIndex[1]
				P_ij = P_InitMat[i][j]
			
				# break if the constraints are satisfied
				# P_ij == 0 means that both teams are in different pool 
				if i <= j and int(P_ij) == 0:
					# apply prohibition constraints
						
					##### apply type distribution constraints #####
					# create temporary P matrix if the transIndex is applied 
					
					P_TransMatTmp = np.copy(P_InitMat)
					P_TransMatTmp[transIndex,:] = P_TransMatTmp[list(reversed(transIndex)),:]  # change two columns according to transIndex
					P_TransMatTmp[:,transIndex] = P_TransMatTmp[:,list(reversed(transIndex))] # change two rows according to transIndex

					poolDistributionTmp = create_pool_distribution_from_matrix(P_TransMatTmp, teamNbr, poolNbr, poolSize, teams)
# 					logging.debug("  poolDistributionTmp: \n%s" %poolDistributionTmp)

					statusProhibitionConstraints = check_prohibition_constraints(prohibitionConstraints, poolDistributionTmp)
					logging.debug("	statusProhibitionConstraints: %s" %statusProhibitionConstraints)
					

					statusTypeDistributionConstraints = check_type_distribution_constraints(typeDistributionConstraints, poolDistributionTmp)
					logging.debug("	statusTypeDistributionConstraints: %s" %statusTypeDistributionConstraints)

					# if the transformed matrix fulfills the type distribution constraints
					if statusProhibitionConstraints == 0 and statusTypeDistributionConstraints == 0:
						logging.debug("  i: %s, j: %s" %(i, j))
						logging.debug("  iterConstraint: %s" %(iterConstraint))
						break
		

			
			P_TransMat = np.copy(P_InitMat)
	
			# change two columns according to transIndex
			P_TransMat[transIndex,:] = P_TransMat[list(reversed(transIndex)),:]
			# change two rows according to transIndex
			P_TransMat[:,transIndex] = P_TransMat[:,list(reversed(transIndex))]
	# 		logging.debug("  P_InitMat: \n%s" %P_InitMat)


			V_oriValue = calculate_V_value(P_InitMat, D_Mat)
			logging.debug("  V_oriValue: %s" %V_oriValue)
	
			V_transValue = calculate_V_value(P_TransMat, D_Mat)
			logging.debug("  V_transValue: %s" %V_transValue)
	
			deltaV = V_oriValue - V_transValue
			logging.debug("  deltaV: %s" %deltaV)
			
			# temperature function 
			# reinitialize temperature in the middle of loop
			if nbIter == int(iter/2):
				T_Value = 0.1 * initDistance
			# reinitilize temperature if deltaV is at least 5% of V_oriValue
			if deltaV >= 0.05 * V_oriValue:
				T_Value = 0.1 * initDistance

			if deltaV <= 0:
				pass
			else:
				randValue = random.random()
# 				logging.debug("randValue: %s" %randValue)
	
				expValue = math.exp(-deltaV/T_Value)
# 				logging.debug("expValue: %s" %expValue)
	
				if randValue <= expValue:
					pass
				else:
					P_InitMat = P_TransMat

		logging.debug("")

# 		logging.debug("  P_InitMat: \n%s" %P_InitMat)
# 		logging.debug("  P_TransMat: \n%s" %P_TransMat)
# 		logging.debug("  P_TransMatTmp: \n%s" %P_TransMatTmp)

		poolDistributionTest = create_pool_distribution_from_matrix(P_InitMat, teamNbr, poolNbr, poolSize, teams)
		logging.debug("  poolDistributionTest: \n%s" %poolDistributionTest)


		return P_InitMat
		
	except Exception as e:
		show_exception_traceback()






"""
Function to get P Matrix for Round Trip and One Way Match Equitable Scenario Without Constraint
"""
def get_p_matrix_for_round_trip_match_equitable_without_constraint(P_InitMat, D_Mat, iter,  teamNbr):
	try:		
		# calculate initial distance
		initDistance = calculate_V_value_equitable(P_InitMat, D_Mat)
		logging.debug("  initDistance: %s" %initDistance)
	
		# calculate initial T_Value
		T_Value = 0.1 * initDistance
		logging.debug("  T_Value initial: %s" %T_Value)

		for nbIter in range(iter):
			logging.debug("  ----------------------------------------------------------------------------------------------------")
			logging.debug("  nbIter: %s" %nbIter)
			logging.debug("  ----------------------------------------------------------------------------------------------------")
	
			# Function T_value
			T_Value *= 0.99
			logging.debug("  T_Value current: %s" %T_Value)
	
			### get index to change row and column
			while True:
				transIndex = random.sample(range(teamNbr), 2)
			
				i = transIndex[0]
				j = transIndex[1]
				P_ij = P_InitMat[i][j]
			
				if i <= j and int(P_ij) == 0:
					logging.debug("  i: %s, j: %s" %(i, j))
					break
	# 			
			P_TransMat = np.copy(P_InitMat)
	
			# change two columns according to transIndex
			P_TransMat[transIndex,:] = P_TransMat[list(reversed(transIndex)),:]
			# change two rows according to transIndex
			P_TransMat[:,transIndex] = P_TransMat[:,list(reversed(transIndex))]
	# 		logging.debug("  P_InitMat: \n%s" %P_InitMat)
	# 		logging.debug("  P_TransMat: \n%s" %P_TransMat)
	# 
			V_oriValue_equitable = calculate_V_value_equitable(P_InitMat, D_Mat)
			logging.debug("  V_oriValue_equitable: %s" %V_oriValue_equitable)
	
			V_transValue_equitable = calculate_V_value_equitable(P_TransMat, D_Mat)
			logging.debug("  V_transValue_equitable: %s" %V_transValue_equitable)
	
			deltaV_equitable = V_oriValue_equitable - V_transValue_equitable
			logging.debug("  deltaV_equitable: %s" %deltaV_equitable)
			
			######################################################################################################
			V_oriValue = calculate_V_value(P_InitMat, D_Mat)
			logging.debug("  V_oriValue: %s" %V_oriValue)
			V_transValue = calculate_V_value(P_TransMat, D_Mat)
			logging.debug("  V_transValue: %s" %V_transValue)
			deltaV = V_oriValue - V_transValue
			logging.debug("  deltaV: %s" %deltaV)
			######################################################################################################
			
			
			
			# temperature function 
			# reinitialize temperature in the middle of loop
			if nbIter == int(iter/2):
				T_Value = 0.1 * initDistance
			# reinitilize temperature if deltaV is at least 5% of V_oriValue
			if deltaV >= 0.05 * V_oriValue:
				T_Value = 0.1 * initDistance
	# 
			if deltaV_equitable <= 0:
				pass
			else:
				randValue = random.random()
# 				logging.debug("  randValue: %s" %randValue)
	
				expValue = math.exp(-deltaV_equitable/T_Value)
# 				logging.debug("  expValue: %s" %expValue)
	
				if randValue <= expValue:
					pass
				else:
					P_InitMat = P_TransMat

		return P_InitMat
		
	except Exception as e:
		show_exception_traceback()



"""
Function to get P Matrix for Round Trip and One Way Match Equitable Scenario With Constraint
"""
def get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId):
	try:		
		# calculate initial distance
		initDistance = calculate_V_value_equitable(P_InitMat, D_Mat)
		logging.debug("  initDistance: %s" %initDistance)
	
		# calculate initial T_Value
		T_Value = 0.1 * initDistance
		logging.debug("  T_Value initial: %s" %T_Value)

		logging.debug("  iterConstraint: %s" %iterConstraint)

		# get indexes of prohibition constraints
# 		indexesProhibitionConstraints = getIndexesProhibitionConstraints(prohibitionConstraints, teams)
# 		logging.debug("  indexesProhibitionConstraints: %s" %indexesProhibitionConstraints)
		
		# get indexes of type distribution constraints
# 		indexesTypeDistributionConstraints = getIndexesTypeDistributionConstraints(typeDistributionConstraints, teams)
# 		logging.debug("  indexesTypeDistributionConstraints: %s" %indexesTypeDistributionConstraints)



		for nbIter in range(iter):
			logging.debug("  ----------------------------------------------------------------------------------------------------")
			logging.debug("  nbIter: %s" %nbIter)
			logging.debug("  ----------------------------------------------------------------------------------------------------")
	
			# Function T_value
			T_Value *= 0.99
			logging.debug("  T_Value current: %s" %T_Value)
	
	
			# list of prohibited constraints
# 			rulesProhibitionConstraints = create_rules_for_prohibition_constraints(indexesProhibitionConstraints, P_InitMat)
# 			logging.debug("  rulesProhibitionConstraints: %s" %(rulesProhibitionConstraints))

			# list of type distribution constraints
# 			rulesTypeDistributionConstraints = []
# 			for type, indexConstraint in indexesTypeDistributionConstraints.items():
# 				rulesTypeDistributionConstraints += indexConstraint
# 			logging.debug("  rulesTypeDistributionConstraints: %s" %(rulesTypeDistributionConstraints))

	
			### get index to change row and column
			while True:
				if iterConstraint == 0:
					logging.debug("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!")
					logging.debug("Failure to create interchange rows and  columns (i, j) which fulfills all constraints")
					logging.debug("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!")
# 					# update status job failure
					update_job_status(reportId, -1)
# 					send_email_to_user_failure(userId)
					send_email_to_user_failure(userId, reportId)
					sys.exit()
				iterConstraint -= 1

				transIndex = random.sample(range(teamNbr), 2)
			
				i = transIndex[0]
				j = transIndex[1]
				P_ij = P_InitMat[i][j]
			
			
				# break if the constraints are satisfied
				# P_ij == 0 means that both teams are in different pool 
				if i <= j and int(P_ij) == 0:
					# apply prohibition constraints
# 					if transIndex not in rulesProhibitionConstraints:

						##### apply type distribution constraints #####
						# create temporary P matrix if the transIndex is applied 
						P_TransMatTmp = np.copy(P_InitMat)
						P_TransMatTmp[transIndex,:] = P_TransMatTmp[list(reversed(transIndex)),:]  # change two columns according to transIndex
						P_TransMatTmp[:,transIndex] = P_TransMatTmp[:,list(reversed(transIndex))] # change two rows according to transIndex

						poolDistributionTmp = create_pool_distribution_from_matrix(P_TransMatTmp, teamNbr, poolNbr, poolSize, teams)
						logging.debug("  poolDistributionTmp: \n%s" %poolDistributionTmp)
						
						statusProhibitionConstraints = check_prohibition_constraints(prohibitionConstraints, poolDistributionTmp)
						logging.debug("  statusProhibitionConstraints: %s" %statusProhibitionConstraints)


						statusTypeDistributionConstraints = check_type_distribution_constraints(typeDistributionConstraints, poolDistributionTmp)
						logging.debug("	statusTypeDistributionConstraints: %s" %statusTypeDistributionConstraints)

						# if the transformed matrix fulfills the type distribution constraints
						if statusProhibitionConstraints == 0 and statusTypeDistributionConstraints == 0:
							logging.debug("  i: %s, j: %s" %(i, j))
							logging.debug("  iterConstraint: %s" %(iterConstraint))
							break
						
# 						if i not in rulesTypeDistributionConstraints and j not in rulesTypeDistributionConstraints:
# 							logging.debug("  i: %s, j: %s" %(i, j))
# 							logging.debug("  iterConstraint: %s" %(iterConstraint))
# 							break

			P_TransMat = np.copy(P_InitMat)
	
			# change two columns according to transIndex
			P_TransMat[transIndex,:] = P_TransMat[list(reversed(transIndex)),:]
			# change two rows according to transIndex
			P_TransMat[:,transIndex] = P_TransMat[:,list(reversed(transIndex))]
	# 		logging.debug("  P_InitMat: \n%s" %P_InitMat)
	# 		logging.debug("  P_TransMat: \n%s" %P_TransMat)
	# 
			V_oriValue_equitable = calculate_V_value_equitable(P_InitMat, D_Mat)
			logging.debug("  V_oriValue_equitable: %s" %V_oriValue_equitable)
	
			V_transValue_equitable = calculate_V_value_equitable(P_TransMat, D_Mat)
			logging.debug("  V_transValue_equitable: %s" %V_transValue_equitable)
	
			deltaV_equitable = V_oriValue_equitable - V_transValue_equitable
			logging.debug("  deltaV_equitable: %s" %deltaV_equitable)
			
# 			######################################################################################################
			V_oriValue = calculate_V_value(P_InitMat, D_Mat)
			logging.debug("  V_oriValue: %s" %V_oriValue)
			V_transValue = calculate_V_value(P_TransMat, D_Mat)
			logging.debug("  V_transValue: %s" %V_transValue)
			deltaV = V_oriValue - V_transValue
			logging.debug("  deltaV: %s" %deltaV)
# 			######################################################################################################
			
			
			
			# temperature function 
			# reinitialize temperature in the middle of loop
			if nbIter == int(iter/2):
				T_Value = 0.1 * initDistance
			# reinitilize temperature if deltaV is at least 5% of V_oriValue
			if deltaV >= 0.05 * V_oriValue:
				T_Value = 0.1 * initDistance
	# 
			if deltaV_equitable <= 0:
				pass
			else:
				randValue = random.random()
# 				logging.debug("  randValue: %s" %randValue)
	
				expValue = math.exp(-deltaV_equitable/T_Value)
# 				logging.debug("  expValue: %s" %expValue)
	
				if randValue <= expValue:
					pass
				else:
					P_InitMat = P_TransMat

		return P_InitMat
		
	except Exception as e:
		show_exception_traceback()

"""
Function to eliminate phantom team members in pool distribution
"""
def eliminate_phantom_in_pool_distribution(poolDistribution):
	try:
		poolDistributionReturn = {}
		
		for pool, contentPool in poolDistribution.items():
			# remove phantom teams
			testPhantomId = any(i < 0 for i in contentPool)
			
			if testPhantomId:
# 			if -1 in contentPool:
				teamMembers = []
				for member in contentPool:
					if member > 0:
						teamMembers.append(member)

				poolDistributionReturn[pool] = teamMembers
			else:
				poolDistributionReturn[pool] = contentPool
		
		return poolDistributionReturn
	except Exception as e:
		show_exception_traceback()


"""
Function to get info pool (pool size and team numbers in each pool) even when there is any phantom teams
"""
def get_info_pool_from_pool_distribution(poolDistribution):
	try:
		
		infoPool = {}

		for pool, members in poolDistribution.items():
			poolSize = len(members)
			if poolSize not in infoPool:
				infoPool[poolSize] = 1
			else:
				infoPool[poolSize] += 1
				


		return infoPool

	except Exception as e:
		show_exception_traceback()





"""
Function to get reference pool distribution from DB
"""
def create_reference_pool_distribution_from_db(teams, poolSize):
	try:
		poolDistributionReference = {"status": "yes", "data": {}}
		phantomTeams = []

# 		logging.debug(" teams: %s" %sorted(teams))
# 		logging.debug(" poolSize: %s" %poolSize)

		listChars = []

		# construct pool distribution without phantom teams
		for team in teams:
			# check if not phantom team (separate beween normal teams and phantom teams)
			if int(team) > 0:
				sql = "select id, poule from entite where id=%s"%team
				teamId, poolId = db.fetchone_multi(sql)
	
				# check if there is poolId, if not exist return function
				if (poolId is None) or (poolId == ""):
					poolDistributionReference["status"] = "no"
					return poolDistributionReference

				#############################################################################################################
				# Patch for front, convert from pool letter given by users to number # FIXME !!!!
				if poolId not in listChars:
					listChars.append(poolId)
				poolId = (listChars.index(poolId)) + 1
# 				logging.debug(" poolId: %s" %poolId)
				#############################################################################################################


				if poolId not in poolDistributionReference["data"]:
					poolDistributionReference["data"][poolId] = [teamId]
				else:
					poolDistributionReference["data"][poolId].append(teamId)
			else:
				phantomTeams.append(team)

		# calculate the max pool size if the distribution is not uniform (there are phantom members)
		maxPoolSizeRef = 0
		poolNbrRef = 0
		for pool, members in poolDistributionReference["data"].items():
			poolNbrRef += 1
# 			logging.debug(" members: %s" %members)
			if maxPoolSizeRef < len(members):
				maxPoolSizeRef = len(members)
		poolDistributionReference["maxPoolSizeRef"] = maxPoolSizeRef
		poolDistributionReference["poolNbrRef"] = poolNbrRef
		logging.debug(" maxPoolSizeRef: %s" %maxPoolSizeRef)
		logging.debug(" poolNbrRef: %s" %poolNbrRef)

		# add phantom teams to the created distribution
		if len(phantomTeams) > 0:
			poolDistributionReferenceTmp = dict.copy(poolDistributionReference["data"])
			for pool, poolTeams in poolDistributionReferenceTmp.items():
# 				if len(poolTeams) < poolSize:
				if len(poolTeams) < maxPoolSizeRef:
					sizeDiff = poolSize - len(poolTeams)
					for i in range(sizeDiff):
						phantomTeam = phantomTeams.pop()
						poolDistributionReference["data"][pool].append(phantomTeam)
					
					

# 		logging.debug(" teams: %s" %teams)
# 		logging.debug(" phantomTeams: %s" %phantomTeams)
# 		logging.debug(" len teams: %s" %len(teams))
		return poolDistributionReference
	except Exception as e:
		show_exception_traceback()


"""
Function to create distance matrix from an XLS file
"""
def create_distance_matrix_from_xl(inputFile):
	try:
		logging.debug("####################################### READ XLSX DISTANCE MATRIX ##############################################")
	# 		wb = load_workbook('/home/henz/project/ffbb/matrice_od_144_real.xlsx')
		wb = load_workbook(inputFile)
		ws = wb.active
		
	# 		dataObj = ws['C5:EP148']
		dataObj = ws['B4:AW51']
		listValues = []
		for rowEl in dataObj:
			listValuesRow = []
			for el in rowEl:
				valueTmp = el.value
				listValuesRow.append(valueTmp)
			listValues.append(listValuesRow)
	# 		logging.debug("listValues: %s" %str(listValues))
		
		D_Mat = np.array(listValues)
		logging.debug("shape D_Mat: %s" %str(D_Mat.shape))
# 		logging.debug("D_Mat: \n%s" %str(D_Mat))
	except Exception as e:
		show_exception_traceback()

	return D_Mat

"""
Function to get coordinates for longitude and latitude 
"""
def get_coordinates_from_city_id(entityId):

	try:
		# first get from table entite
		sql = "select latitude, longitude, id_ville_france from entite where id=%s"%entityId
		lat, lon, cityId = db.fetchone_multi(sql)
		
		# if not found try in table villes_france_free
		if (lat in [None, 0]) or (lon in [None, 0] ) and cityId != None:
			sql = "select ville_latitude_deg, ville_longitude_deg from villes_france_free where ville_id=%s"%cityId
			lat, lon  = db.fetchone_multi(sql)
			
			# update coordinates in table entite
			sql = "update entite set longitude=%s, latitude=%s where id=%s" %(lon, lat, entityId)
			logging.debug("sql: %s" %(sql))
			db.execute(sql)
			db.commit()
			
		coord = "%s,%s"%(lat, lon)
		return coord

	except Exception as e:
		show_exception_traceback()
		
"""
Function to probe HERE web service and fill in table trajet
"""
def get_distance_travel_time_from_here_ws(cityIdDepart, cityIdDestination, coordDepart, coordDestination):
	try:
		
		hereUrl = "http://route.api.here.com/routing/7.2/calculateroute.json"
		params = { 	"waypoint0": coordDepart,
					"waypoint1": coordDestination,
					"app_id": config.HERE.AppId,
					"app_code": config.HERE.AppCode,
					"mode": "fastest;car;traffic:disabled"
				}

		resp = requests.get(url=hereUrl, params=params)
		data = json.loads(resp.text)
					
		# get distance from HERE response
		if ( data["response"]):
			distance = data['response']['route'][0]['summary']['distance']
			travelTime = data['response']['route'][0]['summary']['baseTime']


		# insert to table trajet
		dateCreation = datetime.datetime.now().date()
# 		logging.debug("dateCreation: %s" %dateCreation)

		sql = """insert into trajet (depart, destination, distance, duree, date_creation) 
					values( %(depart)s, %(destination)s, %(distance)s, %(duree)s, '%(date_creation)s' ) 
				"""%{
						"depart": cityIdDepart,
						"destination": cityIdDestination,
						"distance": distance,
						"duree": travelTime,
						"date_creation": dateCreation,
					}
# 		logging.debug("sql: %s" %sql)
		db.execute(sql)
		db.commit()


		returnDict = {"distance": distance, "travelTime": travelTime}
		
		return returnDict
	
	except Exception as e:
		show_exception_traceback()
		

"""
Function to create distance matrix from DB
"""
def create_distance_matrix_from_db(teams):
	try:
		# get size for the matrix
		teamNbr = len(teams)
		
		# Initialize Distance matrix D_Mat
		D_Mat = np.zeros((teamNbr, teamNbr))
		
		# fill in the distance matrix
		for indexDepart, depart in enumerate(teams):
		
			# get destination cities
			destinations = list(teams) # make a copy of teams list
# 	 		logging.debug("destinations: %s" %destinations)
			
			for indexDestination, destination in enumerate(destinations):
				
				# do nothing if depart = destination
				if depart == destination:
					distance = 0
				else:
					# get distance from table trajet
					sql = "select distance from trajet where depart=%s and destination=%s "%(depart, destination)
# 					logging.debug("sql: %s" %sql)
					distance = db.fetchone(sql)
# 					logging.debug("\n")
# 					logging.debug("distance DB: %s" %distance)
					
					# call HERE server if distance is None (not found in the table trajet)
					if distance == None:
						logging.debug("depart: %s" %depart)
						logging.debug("destination: %s" %destination)
						
						## get latitude and longitude for the depart team
						coordDepart = get_coordinates_from_city_id(depart)
						logging.debug("coordDepart: %s" %coordDepart)
						
						## get latitude and longitude for the destination team
						coordDestination = get_coordinates_from_city_id(destination)
						logging.debug("coordDestination: %s" %coordDestination)
						
						# get distance and travel time from HERE web service
						resultsHere = get_distance_travel_time_from_here_ws(depart, destination, coordDepart, coordDestination)
						logging.debug("resultsHere: %s" %resultsHere)

						# get distance from results Here
						distance = resultsHere["distance"]
				D_Mat[indexDepart][indexDestination] = distance
	
# 		logging.debug("D_Mat: \n%s" %D_Mat)
		return D_Mat

# 		logging.debug("D_Mat: %s" %D_Mat)
# 		np.savetxt("/tmp/d_mat_%s.csv"%teamNbr, D_Mat, delimiter=",", fmt='%d') # DEBUG
	except Exception as e:
		show_exception_traceback()



"""
Function to create initilization matrix without constraint
"""
# def create_init_matrix_without_constraint(teamNbr, poolNbr, poolSize, varTeamNbrPerPool ):
def create_init_matrix_without_constraint(teamNbr, poolNbr, poolSize):

	try:
		logging.debug("-------------------------------------- CREATE INIT MATRIX WITHOUT CONSTRAINT --------------------------------" )
		# Initialisation matrix P
		P_InitMat = np.zeros((teamNbr, teamNbr))
		
		# determine max and min pool size from normal pool size and variation team number per pool
# 		poolSizeMax = poolSize + varTeamNbrPerPool
# 		poolSizeMin = poolSize - varTeamNbrPerPool
		
		logging.debug("teamNbr: %s" %teamNbr)
		logging.debug("poolNbr: %s" %poolNbr)
		logging.debug("poolSize: %s" %poolSize)
# 		logging.debug("varTeamNbrPerPool: %s" %varTeamNbrPerPool)
# 		logging.debug("poolSizeMax: %s" %poolSizeMax)
# 		logging.debug("poolSizeMin: %s" %poolSizeMin)

		# generate a random value for each team
		teamRandomValues = [round(random.random() * 100) for i in range(teamNbr)]
		logging.debug("teamRandomValues: %s" %teamRandomValues)
		
		# get the index values of the sorted random values
		indexSortedTeamRandomValues = sorted( range(len(teamRandomValues)), key=lambda k: teamRandomValues[k] )
		

# 		logging.debug("teamNbr: %s" %teamNbr)
# 		logging.debug("poolNbr: %s" %poolNbr)
# 		logging.debug("poolSize: %s" %poolSize)
		
		
		
		# attribute pool number to the sorted team values
		teamPoolSorted = []
		for i in range(poolNbr):
			tempList = [i+1]*poolSize
			teamPoolSorted += tempList
		
		# get the pool number of the original (unsorted) team values
		teamPoolResult = [0] * teamNbr
		for i in range(teamNbr):
			teamPoolResult[indexSortedTeamRandomValues[i]] = teamPoolSorted[i]
		logging.debug("teamPoolResult: %s" %teamPoolResult)
		logging.debug("len teamPoolResult: %s" %len(teamPoolResult))
		
		#####################################################################################################
		# take into account variation of team number in a pool
		#####################################################################################################
# 		teamPoolResult = adjust_pool_attribution_based_on_pool_variation(teamPoolResult, poolNbr, poolSize, varTeamNbrPerPool)
# 		logging.debug("teamPoolResult: %s" %teamPoolResult)
	
		#####################################################################################################
		
		# get index of the teams with the same pool number (create 2D Matrix from list)
		for indexCurPool, curPoolNbr in enumerate(teamPoolResult):
			sameCurValueIndex =  [i for i, x in enumerate(teamPoolResult) if x == curPoolNbr]
			sameCurValueIndex.remove(indexCurPool)
		
			P_InitMat[indexCurPool, sameCurValueIndex] = 1
		# 
	except Exception as e:
		show_exception_traceback()

	return P_InitMat

"""
Function to get prohibition constraints
"""
def get_prohibition_constraints(prohibitionDict):
	try:
		# check if the prohibition dictionary is empty or not
		if any(prohibitionDict):
			prohibitionConstraints = {"status": "yes", "data": []} 
			
			for constraintNbr, constraint in prohibitionDict.items():
				# remove white spaces
				team1 = int(constraint[0].strip())
				team2 = int(constraint[1].strip())
				
				prohibitionConstraints["data"].append([team1, team2])
			
		else:
			prohibitionConstraints = {"status": "no", "data": []} 
		

		return prohibitionConstraints
	except Exception as e:
		show_exception_traceback()


"""
Function to get type distribution constraints
"""
def get_type_distribution_constraints(typeDistributionDict):
	try:
		# check if the type distribution dictionary is empty or not
		if any(typeDistributionDict):
# 			typeDistributionConstraints = {"status": "yes", "data": {"espoir":  [7968, 7969]}}
			typeDistributionConstraints = {"status": "yes", "data": {} }
			
			for teamType, members in typeDistributionDict.items():
				typeDistributionConstraints["data"].update({teamType : members})
			
		else:
			typeDistributionConstraints = {"status": "no", "data": {}}

		return typeDistributionConstraints
		
	except Exception as e:
		show_exception_traceback()


"""
Function to check prohibition constraints
Return 1 if failure (any prohibition constraint in the pool distribution)
Return 0 if success (pass the prohibition constraints)
"""
def check_prohibition_constraints(prohibitionConstraints, poolDistribution):
	try:
		for constraint in prohibitionConstraints:
			constraintFirstTeam = int(constraint[0])
			constraintSecondTeam = int(constraint[1])
		
			for pool, poolMembers in poolDistribution.items():
				if constraintFirstTeam in poolMembers and constraintSecondTeam in poolMembers:
					return 1
				
		return 0
		
	except Exception as e:
		show_exception_traceback()
		

"""
Function to check if list 1 is a sublist of list 2 or not
return True if yes
return False if not
"""
def list1_is_sublist_of_list2(list1, list2):
	try:
		for memberList1 in list1:
			if memberList1 not in list2:
				return False

		return True
	except Exception as e:
		show_exception_traceback()
		
"""
Function to distribute team members (type distribution constraint)
"""
def distribute_team_members_type_distribution_constraints(poolNbr, inputConstraintNbr):
	try:
		result = [0] * poolNbr

		for i in range(inputConstraintNbr):
			indexResult = i
				
			while True:	
				if indexResult > len(result)-1:			
					indexResult -= len(result)
				else:
					break
			result[indexResult] += 1

		return result

	except Exception as e:
		show_exception_traceback()

"""
Function to check type distribution constraints
Return 1 if failure (for any distribution type, not all members are in the same pool)
Return 0 if success (all the type distribution constraints are fulfilled)
"""
def check_type_distribution_constraints(typeDistributionConstraints, poolDistribution):
	try:
# 		logging.debug("typeDistributionConstraints: %s" %typeDistributionConstraints)
# 		logging.debug("poolDistribution: %s" %poolDistribution)

		# get pool number
		poolNbr = len(poolDistribution.keys())

		for constraintType, constraintTeamMembers in typeDistributionConstraints.items():
			constraintTeamMembersNbr = len(constraintTeamMembers)
# 			logging.debug("constraintTeamMembers: %s" %constraintTeamMembers)
# 			logging.debug("constraintTeamMembersNbr: %s" %constraintTeamMembersNbr)

			expectedMemberDistribution = distribute_team_members_type_distribution_constraints(poolNbr, constraintTeamMembersNbr)
# 			logging.debug("expectedMemberDistribution: %s" %expectedMemberDistribution)

			currentMemberDistribution = []
			for pool, poolMembers in poolDistribution.items():
				
# 				logging.debug("poolMembers: %s" %poolMembers)
# 				logging.debug("poolNbr: %s" %poolNbr)

				# check for each constraintTeamMember
				constraintTeamMembers_inPoolMembersNbr = 0
				for constraintTeamMember in constraintTeamMembers:
					if int(constraintTeamMember) in poolMembers:
						constraintTeamMembers_inPoolMembersNbr += 1
				currentMemberDistribution.append(constraintTeamMembers_inPoolMembersNbr)
# 				logging.debug("constraintTeamMembers_inPoolMembersNbr: %s" %constraintTeamMembers_inPoolMembersNbr)

			# sort current member distribution
			currentMemberDistribution = sorted(currentMemberDistribution, reverse=True)
# 			logging.debug("currentMemberDistribution: %s" %currentMemberDistribution)
			
			# check if current member distribution equals to expected member distribution
			if(currentMemberDistribution != expectedMemberDistribution):
				return 1
				
		return 0
	except Exception as e:
		show_exception_traceback()

"""
Function to create initilization matrix with constraint
"""
# def create_init_matrix_with_constraint(teamNbr, poolNbr, poolSize, teams, iterConstraint, prohibitionConstraints, typeDistributionConstraints, varTeamNbrPerPool):
def create_init_matrix_with_constraint(teamNbr, poolNbr, poolSize, teams, iterConstraint, prohibitionConstraints, typeDistributionConstraints):

	try:
		logging.debug("-------------------------------------- CREATE INIT MATRIX WITH CONSTRAINT --------------------------------" )

		logging.debug("prohibitionConstraints: %s" %prohibitionConstraints)
		logging.debug("typeDistributionConstraints: %s" %typeDistributionConstraints)
		
		for iterNbr in range(iterConstraint):

			logging.debug("-------------------------------------------------------------------------------------------------------" )
			logging.debug("	iterNbr: %s" %iterNbr)
			logging.debug("-------------------------------------------------------------------------------------------------------" )

			# Initialisation matrix P
			P_InitMat = np.zeros((teamNbr, teamNbr))
			
			# generate a random value for each team
			teamRandomValues = [round(random.random() * 100) for i in range(teamNbr)]
# 			logging.debug("	teamRandomValues: %s" %teamRandomValues)
			
			# get the index values of the sorted random values
			indexSortedTeamRandomValues = sorted( range(len(teamRandomValues)), key=lambda k: teamRandomValues[k] )
	# 		logging.debug("indexSortedTeamRandomValues: %s" %indexSortedTeamRandomValues)
			
			# attribute pool number to the sorted team values
			teamPoolSorted = []
			for i in range(poolNbr):
				tempList = [i+1]*poolSize
				teamPoolSorted += tempList
	# 		logging.debug("teamPoolSorted: %s" %teamPoolSorted)
			
			# get the pool number of the original (unsorted) team values
			teamPoolResult = [0] * teamNbr
			for i in range(teamNbr):
				teamPoolResult[indexSortedTeamRandomValues[i]] = teamPoolSorted[i] 
# 			logging.debug("	len teamPoolResult: %s" %len(teamPoolResult))
			logging.debug("	teamPoolResult: %s" %teamPoolResult)
	# 		logging.debug("teams: %s" %teams)

			# create pool distribution
			poolDistribution = {}
			for i in range(teamNbr):
				team = teams[i]
				pool = teamPoolResult[i]
				
				if pool not in poolDistribution:
					poolDistribution[pool] = [team]
				else:
					poolDistribution[pool].append(team)

			logging.debug("	poolDistribution: %s" %poolDistribution)
			
			# apply prohibition constraints to the pool distribution
			statusProhibitionConstraints = check_prohibition_constraints(prohibitionConstraints, poolDistribution)
			logging.debug("	statusProhibitionConstraints: %s" %statusProhibitionConstraints)

			# apply type distribution constraints to the pool distribution
			statusTypeDistributionConstraints = check_type_distribution_constraints(typeDistributionConstraints, poolDistribution)
			logging.debug("	statusTypeDistributionConstraints: %s" %statusTypeDistributionConstraints)
			

			# if the initial P Matrix does not have any problem with the distribution constraints
			if statusProhibitionConstraints == 0 and statusTypeDistributionConstraints == 0:
				break
		

		# create P Init Matrix only if both status constraints are 0 (success)
		if statusProhibitionConstraints == 0 and statusTypeDistributionConstraints == 0:
			# get index of the teams with the same pool number (create 2D Matrix from list)
			for indexCurPool, curPoolNbr in enumerate(teamPoolResult):
				sameCurValueIndex =  [i for i, x in enumerate(teamPoolResult) if x == curPoolNbr]
				sameCurValueIndex.remove(indexCurPool)
			
				P_InitMat[indexCurPool, sameCurValueIndex] = 1
				

			np.savetxt("/tmp/p_init_mat_with_constraint.csv", P_InitMat, delimiter=",", fmt='%d')
			return {"success": True, "data": P_InitMat}
		else:
			return {"success": False, "data": None}
		# 

	except Exception as e:
		show_exception_traceback()

"""
Function to create phantom distance matrix from distance matrix
"""
def create_phantom_distance_matrix(D_Mat, teamNbr, poolNbr, poolSize):
	try:

		D_Mat_phantom = np.zeros((poolNbr*poolSize, poolNbr*poolSize))

		for row in range(teamNbr):
			for col in range(teamNbr):
				D_Mat_phantom[row][col] = D_Mat[row][col]

# 		logging.debug("D_Mat_phantom: \n%s" %(D_Mat_phantom,))
		return D_Mat_phantom

	except Exception as e:
		show_exception_traceback()

"""
Function to create P Matrix from pool distribution
"""
def create_matrix_from_pool_distribution(poolDistribution, teamNbr, teams):
	try:
		# Initialisation matrix P
		P_Mat = np.zeros((teamNbr, teamNbr))
		
# 		logging.debug("poolDistribution: %s" %poolDistribution)
# 		logging.debug("teams: %s" %teams)
		
		# create pool distribution using indexes
		indexesPoolDistribution = {}
		
		for pool, teamMembers in poolDistribution.items():
			indexesPoolDistribution[pool] = []
			
			for member in teamMembers:
				index = teams.index(member)
				indexesPoolDistribution[pool].append(index)
				
		
# 		logging.debug("indexesPoolDistribution: %s" %indexesPoolDistribution)
		
		# fill in P_Mat
		for pool, indexesTeamMembers in indexesPoolDistribution.items():
			for indexFirstMember in indexesTeamMembers:
				indexesOtherMembers =  list(indexesTeamMembers)
				indexesOtherMembers.remove(indexFirstMember)
				for indexSecondMember in indexesOtherMembers:
					P_Mat[indexFirstMember][indexSecondMember] = 1
# 					logging.debug("indexFirstMember: %s" %indexFirstMember)
# 					logging.debug("indexSecondMember: %s" %indexSecondMember)
			
		
# 		logging.debug("P_Mat: \n%s" %P_Mat)
		return P_Mat
	except Exception as e:
		show_exception_traceback()

"""
Function to get coordinates points of pool distribution
"""
def	get_coords_pool_distribution(poolDistribution):
	try:
		poolDistributionCoords = {}
		
		for pool, members in poolDistribution.items():
			poolDistributionCoords[pool] = []
			
			for member in members:
				sql = "select latitude, longitude from entite where id=%s"%member
				lat, lon = db.fetchone_multi(sql)
				poolDistributionCoords[pool].append((lat, lon))
# 				logging.debug(" lat: %s lon: %s" %(lat, lon))
			
		return poolDistributionCoords
	except Exception as e:
		show_exception_traceback()
		
"""
Function to get list of names and cities from list of entity ids
"""		
def get_list_details_from_list_ids_for_entity(listIds):
	try:
		listDetails = {"ids":[], "names": [], "cities": []}
		
		sql = "select id from entite where id in (%s)"%(listIds)
		listDetails["ids"] = db.fetchone_column(sql)

		sql = "select nom from entite where id in (%s)"%(listIds)
		listDetails["names"] = db.fetchone_column(sql)

		sql = "select ville from entite where id in (%s)"%(listIds)
		listDetails["cities"] = db.fetchone_column(sql)
		
# 		logging.debug(" listDetails: %s" %(listDetails,))
		return listDetails
		
		
	except Exception as e:
		show_exception_traceback()
	
	

"""
Function to make variation of team number per pool
"""
def variation_team_number_per_pool(poolsIds, varTeamNbrPerPool):
	try:

		logging.debug(" poolsIds: %s" %(poolsIds,))
		
		poolNbr = len(poolsIds.keys())
		logging.debug(" poolNbr: %s" %(poolNbr,))

		poolsIdsCopy = dict.copy(poolsIds)
		resultPoolsIds = {}

		# if pool number is even
		if poolNbr % 2 == 0:
			logging.debug(" even pool number")
			
			tmpTeams = []
			for index, (pool, teams) in enumerate(poolsIdsCopy.items(), start=1):
# 				logging.debug(" index: %s"%index)
# 				logging.debug(" pool: %s"%pool)
# 				logging.debug(" teams: %s"%teams)
			
				# remove teams from odd number pool
				if index % 2 == 1:
					for i in range(varTeamNbrPerPool):
						tmpTeams.append(teams.pop())
				
				# add teams to even number pool
				if index % 2 == 0:
					teams += tmpTeams
					tmpTeams = []
				resultPoolsIds[pool] = teams

		# if pool number is odd
		if poolNbr % 2 == 1:
			logging.debug(" odd pool number")
		
			tmpTeams = []
			for index, (pool, teams) in enumerate(poolsIdsCopy.items(), start=1):
				# ignore last pool
				if index != poolNbr:
# 					logging.debug(" index: %s"%index)
# 					logging.debug(" pool: %s"%pool)
# 					logging.debug(" teams: %s"%teams)

					# remove teams from odd number pool
					if index % 2 == 1:
						for i in range(varTeamNbrPerPool):
							tmpTeams.append(teams.pop())
					
					# add teams to even number pool
					if index % 2 == 0:
						teams += tmpTeams
						tmpTeams = []
					resultPoolsIds[pool] = teams
				
# 		logging.debug(" resultPoolsIds: %s" %(resultPoolsIds,))

# 		return poolsIds
		return resultPoolsIds
	except Exception as e:
		show_exception_traceback()

"""
Function to send email to user concerning the job finished status
"""
def send_email_to_user(userId, resultId):
	try:
		# get user's email from user id
		sql = "select email from fos_user where id=%s"%userId
		
		TO = db.fetchone(sql)
# 		logging.debug("TO: %s" %TO)

		URL="%s/admin/poules/resultat/%s"%(config.INPUT.MainUrl, resultId)

		SUBJECT = u'mise à disposition de vos résultats de calculs'
		TEXT = u"Bonjour,\n\n" 
		TEXT += u"Le résultat de votre calcul est disponible. "
		TEXT += u"Vous pouvez le consulter en cliquant sur ce lien:\n" 
		TEXT += u"%s"%(URL)
		logging.debug("TEXT: \n%s" %TEXT)
		
		# Gmail Sign In
		gmail_sender = config.EMAIL.Account
		gmail_passwd = config.EMAIL.Password
		
		server = smtplib.SMTP(config.EMAIL.Server, config.EMAIL.Port)
		server.ehlo()
		server.starttls()
		server.login(gmail_sender, gmail_passwd)
		
		
		msg = MIMEText(TEXT)
		msg['Subject'] = SUBJECT
		msg['From'] = gmail_sender
		msg['To'] = TO
		
		
		server.sendmail(gmail_sender, [TO], msg.as_string())
		server.quit()	


	except Exception as e:
		show_exception_traceback()
	

"""
Function to send email to user when there is no results (there are too many constraints)
"""
# def send_email_to_user_failure(userId):
def send_email_to_user_failure(userId, reportId):
	try:
# 		sql = "select nom from rapport where id=%s"%reportId
		sql = "select nom from parametres where id=%s"%reportId
		reportName = db.fetchone(sql)
		
		# get user's email from user id
		sql = "select email from fos_user where id=%s"%userId
		
		TO = db.fetchone(sql)
# 		logging.debug("TO: %s" %TO)

		SUBJECT = u'mise à disposition de vos résultats de calculs'
		TEXT = u"Bonjour,\n\n" 
# 		TEXT += u"Aucun résultat n'est disponible pour vos critères de sélection. "
		TEXT += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName
		TEXT += u"Veuillez modifier vos critères et contraintes et relancer un calcul. " 
		logging.debug("TEXT: \n%s" %TEXT)
		
		# Gmail Sign In
		gmail_sender = config.EMAIL.Account
		gmail_passwd = config.EMAIL.Password
		
		server = smtplib.SMTP('smtp.gmail.com', 587)
		server.ehlo()
		server.starttls()
		server.login(gmail_sender, gmail_passwd)
		
		
		msg = MIMEText(TEXT)
		msg['Subject'] = SUBJECT
		msg['From'] = gmail_sender
		msg['To'] = TO
		
		
		server.sendmail(gmail_sender, [TO], msg.as_string())
		server.quit()	

		sys.exit()

	except Exception as e:
		show_exception_traceback()


"""
Function control provided params by user
"""
# def control_params_match_plateau(userId, teamNbr, poolNbr):
def control_params_match_plateau(userId, teamNbr, poolNbr, reportId):
	try:
# 		sql = "select nom from rapport where id=%s"%reportId
		sql = "select nom from parametres where id=%s"%reportId
		reportName = db.fetchone(sql)

		TEXT = u"Bonjour,\n\n" 
# 		TEXT += u"Aucun résultat n'est disponible pour vos critères de sélection. "
		TEXT += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName

		# team number has to be the multiplication of 9 (9, 18, 27)
		if teamNbr % 9 != 0:
			TEXT += u"Veuillez assurer que le nombre de ligne dans votre fichier correspond au match plateau. " 

			send_email_to_user_failure_with_text(userId, TEXT)
		
		# pool number has to be 9
		poolSize = int(teamNbr/poolNbr)
		if poolSize != 9:
			TEXT += u"Veuillez assurer que le nombre de poule selectionné correspond au nombre de ligne dans votre fichier. " 

			send_email_to_user_failure_with_text(userId, TEXT)


	except Exception as e:
		show_exception_traceback()

"""
Function to send email to user when the provided params are unexpected (for match plateau)
"""
def send_email_to_user_failure_with_text(userId, TEXT):
	try:
		# get user's email from user id
		sql = "select email from fos_user where id=%s"%userId
		
		TO = db.fetchone(sql)
# 		logging.debug("TO: %s" %TO)

		SUBJECT = u'mise à disposition de vos résultats de calculs'
		logging.debug("TEXT: \n%s" %TEXT)
		
		# Gmail Sign In
		gmail_sender = config.EMAIL.Account
		gmail_passwd = config.EMAIL.Password
		
		server = smtplib.SMTP('smtp.gmail.com', 587)
		server.ehlo()
		server.starttls()
		server.login(gmail_sender, gmail_passwd)
		
		
		msg = MIMEText(TEXT)
		msg['Subject'] = SUBJECT
		msg['From'] = gmail_sender
		msg['To'] = TO
		
		
		server.sendmail(gmail_sender, [TO], msg.as_string())
		server.quit()	

		sys.exit()

	except Exception as e:
		show_exception_traceback()





"""
Function to save result into DB
"""
def save_result_to_db(launchType, reportId, groupId, results):
	try:
		resultId = -1
		
		if "params" in results:
			# characters substitution for prohibition constraints
			if "interdictions" in results["params"]:
				for indexProhibition, contentProhibition in results["params"]["interdictions"].items():
					for indexName, name in enumerate(contentProhibition["noms"]):
						results["params"]["interdictions"][indexProhibition]["noms"][indexName] = name.replace("'", u"''")
					for indexCity, city in enumerate(contentProhibition["villes"]):
						results["params"]["interdictions"][indexProhibition]["villes"][indexCity] = city.replace("'", u"''")

		
			# characters substitution for type distribution constraints
			if "repartitionsHomogenes" in results["params"]:
				for teamType, contentTypeDistribution in results["params"]["repartitionsHomogenes"].items():
					for indexName, name in enumerate(contentTypeDistribution["noms"]):
						results["params"]["repartitionsHomogenes"][teamType]["noms"][indexName] = name.replace("'", u"''")
					for indexCity, city in enumerate(contentTypeDistribution["villes"]):
						results["params"]["repartitionsHomogenes"][teamType]["villes"][indexCity] = city.replace("'", u"''") 
# 						logging.debug(" city: %s" %(city,))
		
		name = "%s_rapport_%s_groupe_%s"%(launchType , reportId, groupId) 
		km = 0
		travelTime = 0
		creationDate = time.strftime("%Y-%m-%d")
		modificationDate = time.strftime("%Y-%m-%d")
		co2Car = 0
		co2SharedCar = 0
		co2Bus = 0
		costCar = 0
		costSharedCar = 0
		costBus = 0
		
# 		sql = """insert into scenario (id_rapport, nom, kilometres, duree, date_creation, date_modification, 
		sql = """insert into resultats (id_rapport, nom, kilometres, duree, date_creation, date_modification, 
					co2_voiture, co2_covoiturage, co2_minibus, cout_voiture, cout_covoiturage, cout_minibus, details_calcul ) 
			values ( %(reportId)s , '%(name)s', %(km)s, %(travelTime)s,' %(creationDate)s', '%(modificationDate)s',
					%(co2Car)s, %(co2SharedCar)s, %(co2Bus)s, %(costCar)s, %(costSharedCar)s, %(costBus)s, '%(results)s' )
			"""%{	"reportId": reportId, 
					"name": name,
					"km": km,
					"travelTime": travelTime,
					"creationDate": creationDate,
					"modificationDate": modificationDate,
					"co2Car": co2Car,
					"co2SharedCar": co2SharedCar,
					"co2Bus": co2Bus,
					"costCar": costCar,
					"costSharedCar": costSharedCar,
					"costBus": costBus,
					"results": json.dumps(results),
					
				}
# 		logging.debug("sql: %s" %sql)
		db.execute(sql)
		db.commit()
		
		resultId = db.lastinsertedid()
		
		return resultId
	except Exception as e:
		show_exception_traceback()


"""
Function to save result into DB
"""
def save_result_to_db_post_treatment(launchType, reportId, groupId, results):
	try:
		resultId = -1
		
		if "params" in results:
			# characters substitution for prohibition constraints
			if "interdictions" in results["params"]:
				for indexProhibition, contentProhibition in results["params"]["interdictions"].items():
					for indexName, name in enumerate(contentProhibition["noms"]):
						results["params"]["interdictions"][indexProhibition]["noms"][indexName] = name.replace("'", u"''")
					for indexCity, city in enumerate(contentProhibition["villes"]):
						results["params"]["interdictions"][indexProhibition]["villes"][indexCity] = city.replace("'", u"''")

		
			# characters substitution for type distribution constraints
			if "repartitionsHomogenes" in results["params"]:
				for teamType, contentTypeDistribution in results["params"]["repartitionsHomogenes"].items():
					for indexName, name in enumerate(contentTypeDistribution["noms"]):
						results["params"]["repartitionsHomogenes"][teamType]["noms"][indexName] = name.replace("'", u"''")
					for indexCity, city in enumerate(contentTypeDistribution["villes"]):
						results["params"]["repartitionsHomogenes"][teamType]["villes"][indexCity] = city.replace("'", u"''") 
# 						logging.debug(" city: %s" %(city,))
		
		
		# escape single apostrophe for city names
		# ref scenario
		resultsRef = results["scenarioRef"]
		if resultsRef:
			replace_single_quote_for_result(resultsRef["rencontreDetails"])
# 		logging.debug("resultsRef : %s" %resultsRef)

		# optimal scenario
		resultsOptimalWithoutConstraint = results["scenarioOptimalSansContrainte"]
		if resultsOptimalWithoutConstraint:
			replace_single_quote_for_result(resultsOptimalWithoutConstraint["rencontreDetails"])
		
		resultsOptimalWithConstraint = results["scenarioOptimalAvecContrainte"]
		if resultsOptimalWithConstraint:
			replace_single_quote_for_result(resultsOptimalWithConstraint["rencontreDetails"])
			
		# equitable scenario
		resultsEquitableWithoutConstraint = results["scenarioEquitableSansContrainte"]
		if resultsEquitableWithoutConstraint:
			replace_single_quote_for_result(resultsEquitableWithoutConstraint["rencontreDetails"])
			
		
		resultsEquitableWithConstraint = results["scenarioEquitableAvecContrainte"]
		if resultsEquitableWithConstraint:
			replace_single_quote_for_result(resultsEquitableWithoutConstraint["rencontreDetails"])

		
		
		
		name = "%s_rapport_%s_groupe_%s"%(launchType , reportId, groupId) 
		km = 0
		travelTime = 0
		creationDate = time.strftime("%Y-%m-%d")
		modificationDate = time.strftime("%Y-%m-%d")
		co2Car = 0
		co2SharedCar = 0
		co2Bus = 0
		costCar = 0
		costSharedCar = 0
		costBus = 0
		
# 		sql = """insert into scenario (id_rapport, nom, kilometres, duree, date_creation, date_modification, 
		sql = """insert into resultats (id_rapport, nom, kilometres, duree, date_creation, date_modification, 
					co2_voiture, co2_covoiturage, co2_minibus, cout_voiture, cout_covoiturage, cout_minibus, details_calcul ) 
			values ( %(reportId)s , '%(name)s', %(km)s, %(travelTime)s,' %(creationDate)s', '%(modificationDate)s',
					%(co2Car)s, %(co2SharedCar)s, %(co2Bus)s, %(costCar)s, %(costSharedCar)s, %(costBus)s, '%(results)s' )
			"""%{	"reportId": reportId, 
					"name": name,
					"km": km,
					"travelTime": travelTime,
					"creationDate": creationDate,
					"modificationDate": modificationDate,
					"co2Car": co2Car,
					"co2SharedCar": co2SharedCar,
					"co2Bus": co2Bus,
					"costCar": costCar,
					"costSharedCar": costSharedCar,
					"costBus": costBus,
					"results": json.dumps(results),
					
				}
# 		logging.debug("sql: %s" %sql)
		db.execute(sql)
		db.commit()
		
		resultId = db.lastinsertedid()
		
		return resultId
	except Exception as e:
		show_exception_traceback()



"""
Function to get team names from their ids
"""
def get_team_names_from_ids(teamIds):
	try:
		teamNames = []
		
		for teamId in teamIds:
			sql = "select nom from entite where id=%s"%(teamId)
			teamName = db.fetchone(sql)
			teamNames.append(teamName)
	
		return teamNames
	
	except Exception as e:
		show_exception_traceback()

"""
Function to calculate distance from encounters details for match plateau
"""
def calculate_distance_from_encounters_plateau(detailsPlateau):
	try:
		distance = 0

		for pool, contentPool in detailsPlateau.items():
			for day, contentDay in contentPool.items():
				for contentGroup in contentDay:
					distance += contentGroup["distanceGroupeTousParticipants"]

		return distance
	
	except Exception as e:
		show_exception_traceback()

"""
Function to get list of encounters from host team and other teams (for match plateau)
"""
def get_list_encounters_plateau(hostTeamId, hostTeamName, otherTeamsIds, otherTeamNames):
	try:
		results = {	"groupDistance": 0,
					"groupDistanceAllParticipants": 0,
					"groupTravelTime": 0,
					"travelIds": [], 
					"travelNames":  [], 
					"participantsNbr": 0
				}
# 		logging.debug("results: %s "%(results))
		
		# encounter ids
		for otherTeamId in otherTeamsIds:
			travelId = [hostTeamId, otherTeamId]
			results["travelIds"].append(travelId)
			
		# encounter names
		for otherTeamName in otherTeamNames:
			travelName = [hostTeamName, otherTeamName]
			results["travelNames"].append(travelName)
		
		# distance
		for travelId in results["travelIds"]:
			cityTo = travelId[0]
			cityFrom = travelId[1]
			
			sql = "select distance, duree from trajet where depart=%s and destination=%s"%(cityFrom, cityTo)
# 			logging.debug("sql: %s "%(sql))
			distance, travelTime = db.fetchone_multi(sql)
			
			results["groupDistance"] += distance
			results["groupTravelTime"] += travelTime

			sql = "select participants from entite where id=%s"%cityFrom
			participantsNbr = db.fetchone(sql)
			
			results["participantsNbr"] += participantsNbr
# 			logging.debug("distance: %s "%(distance))

			results["groupDistanceAllParticipants"] += (participantsNbr * distance)

		# divide participants number according to number of travel Id
		results["participantsNbr"] = int( results["participantsNbr"]/ len(results["travelIds"]))
		

		return results
		
	except Exception as e:
		show_exception_traceback()


"""
Function to get reference scenario for match plateau
"""
def get_ref_scenario_plateau(teamsIds):
	try:
		refScenario = {"status" : "no", "data": {} }
		
		teamNames = get_team_names_from_ids(teamsIds)
		logging.debug("teamNames: %s "%(teamNames))
		
		listChars = []
		
		for team in teamsIds:
			sql = "select id, nom, ville, code_postal,  poule, ref_plateau from entite where id=%s"%team
			hostTeamId, hostTeamName, hostTeamCity, hostTeamPostalCode, poolId, refPlateau = db.fetchone_multi(sql)

			# escape single quote			
			hostTeamName = hostTeamName.replace("'", u"''")
			hostTeamCity = hostTeamCity.replace("'", u"''")
			
			refPlateau = json.loads(refPlateau)
# 			logging.debug("refPlateau: %s "%(refPlateau))

			firstDay = int(refPlateau["premierJourReception"])
			# continue to next value if value of firstDay is zero
			if firstDay == 0 or firstDay == "0":
				continue

			firstDayFirstTeamName = refPlateau["premierJourEquipe1"]
			firstDaySecondTeamName = refPlateau["premierJourEquipe2"]

			firstDayFirstTeamId = teamsIds[teamNames.index(firstDayFirstTeamName)]
			sql = "select ville, code_postal from entite where id=%s"%firstDayFirstTeamId
			firstDayFirstTeamCity, firstDayFirstTeamPostalCode = db.fetchone_multi(sql)
			
			firstDaySecondTeamId = teamsIds[teamNames.index(firstDaySecondTeamName)]
			sql = "select ville, code_postal from entite where id=%s"%firstDaySecondTeamId
			firstDaySecondTeamCity, firstDaySecondTeamPostalCode = db.fetchone_multi(sql)

			# escape single quote			
			firstDayFirstTeamName = firstDayFirstTeamName.replace("'", u"''")
			firstDaySecondTeamName = firstDaySecondTeamName.replace("'", u"''")
			firstDayFirstTeamCity = firstDayFirstTeamCity.replace("'", u"''")
			firstDaySecondTeamCity = firstDaySecondTeamCity.replace("'", u"''")

			
			listEncountersGroup = get_list_encounters_plateau(hostTeamId, hostTeamName, [firstDayFirstTeamId, firstDaySecondTeamId] , [firstDayFirstTeamName, firstDaySecondTeamName] )
# 			logging.debug("listEncountersGroup: %s "%(listEncountersGroup))

			contentTmp = {	"hoteId": hostTeamId, 
							"hoteNom": hostTeamName,
							"hoteVille": hostTeamCity,
							"hoteCodePostal": hostTeamPostalCode,

							"premierEquipeId": firstDayFirstTeamId, 
							"premierEquipeNom" : firstDayFirstTeamName, 
							"premierEquipeVille" : firstDayFirstTeamCity, 
							"premierEquipeCodePostal" : firstDayFirstTeamPostalCode, 

							"deuxiemeEquipeId": firstDaySecondTeamId , 
							"deuxiemeEquipeNom": firstDaySecondTeamName, 
							"deuxiemeEquipeVille": firstDaySecondTeamCity, 
							"deuxiemeEquipeCodePostal": firstDaySecondTeamPostalCode, 
							
							"distanceGroupe": listEncountersGroup["groupDistance"], 
							"distanceGroupeTousParticipants": listEncountersGroup["groupDistanceAllParticipants"], 
							"dureeGroupe": listEncountersGroup["groupTravelTime"],
							"deplacementsIds": listEncountersGroup["travelIds"], 
							"deplacementsNoms": listEncountersGroup["travelNames"],
							"nbrParticipants": listEncountersGroup["participantsNbr"]
							}
			refScenario["status"] = "yes"

			secondDay = int(refPlateau["deuxiemeJourReception"])
# 			logging.debug("secondDay: %s "%(secondDay))
			# continue to next value if value of firstDay is zero
			if secondDay != 0 and secondDay != "0":
				secondDayFirstTeamName = refPlateau["deuxiemeJourEquipe1"]
				secondDaySecondTeamName = refPlateau["deuxiemeJourEquipe2"]

				secondDayFirstTeamId = teamsIds[teamNames.index(secondDayFirstTeamName)]
				sql = "select ville, code_postal from entite where id=%s"%secondDayFirstTeamId
				secondDayFirstTeamCity, secondDayFirstTeamPostalCode = db.fetchone_multi(sql)

				secondDaySecondTeamId = teamsIds[teamNames.index(secondDaySecondTeamName)]
				sql = "select ville, code_postal from entite where id=%s"%secondDaySecondTeamId
				secondDaySecondTeamCity, secondDaySecondTeamPostalCode = db.fetchone_multi(sql)
			
				# escape single quote			
				secondDayFirstTeamName = secondDayFirstTeamName.replace("'", u"''")
				secondDaySecondTeamName = secondDaySecondTeamName.replace("'", u"''")
				secondDayFirstTeamCity = secondDayFirstTeamCity.replace("'", u"''")
				secondDaySecondTeamCity = secondDaySecondTeamCity.replace("'", u"''")

			#############################################################################################################
			# Patch for front, convert from pool letter given by users to number # FIXME !!!!
			if poolId not in listChars:
				listChars.append(poolId)
			poolId = (listChars.index(poolId)) + 1
# 				logging.debug(" poolId: %s" %poolId)
			#############################################################################################################

			# pool not yet in reference dict
			if poolId not in refScenario["data"]:
				refScenario["data"][poolId] = {}
				refScenario["data"][poolId][firstDay] = [contentTmp]

				if secondDay == 0 or secondDay == "0":
					continue
				
				listEncountersGroup = get_list_encounters_plateau(hostTeamId, hostTeamName, [secondDayFirstTeamId, secondDaySecondTeamId] , [secondDayFirstTeamName, secondDaySecondTeamName] )

				contentTmp = {	"hoteId": hostTeamId, 
								"hoteNom": hostTeamName, 
								"hoteVille": hostTeamCity,
								"hoteCodePostal": hostTeamPostalCode,

								"premierEquipeId": secondDayFirstTeamId, 
								"premierEquipeNom" : secondDayFirstTeamName, 
								"premierEquipeVille" : secondDayFirstTeamCity, 
								"premierEquipeCodePostal" : secondDayFirstTeamPostalCode, 

								"deuxiemeEquipeId": secondDaySecondTeamId , 
								"deuxiemeEquipeNom": secondDaySecondTeamName, 
								"deuxiemeEquipeVille": secondDaySecondTeamCity, 
								"deuxiemeEquipeCodePostal": secondDaySecondTeamPostalCode, 

								"distanceGroupe": listEncountersGroup["groupDistance"], 
								"distanceGroupeTousParticipants": listEncountersGroup["groupDistanceAllParticipants"], 
								"dureeGroupe": listEncountersGroup["groupTravelTime"],
								"deplacementsIds": listEncountersGroup["travelIds"], 
								"deplacementsNoms": listEncountersGroup["travelNames"],
								"nbrParticipants": listEncountersGroup["participantsNbr"]
								}
				refScenario["data"][poolId][secondDay] = [contentTmp]
			# pool already in reference dict
			else:
				# first day reference
				if firstDay in refScenario["data"][poolId]:
					refScenario["data"][poolId][firstDay].append(contentTmp)
				else:
					refScenario["data"][poolId][firstDay] = [contentTmp]
					
				if secondDay == 0 or secondDay == "0":
					continue

				# second day reference
				if secondDay in refScenario["data"][poolId]:
					refScenario["data"][poolId][secondDay].append(contentTmp)
				else:
					refScenario["data"][poolId][secondDay] = [contentTmp]
					
					
		
# 		logging.debug("refScenario: \n%s"%(json.dumps(refScenario)))
# 		logging.debug("\n")
		
		return refScenario
	
	except Exception as e:
		show_exception_traceback()
		


"""
Function to escape single quote
"""
def replace_single_quote_for_result(result):
	try:
		for pool, contentPool in result.items():
			for encounterNbr, contentEncounter in contentPool.items():
				contentEncounter["equipeDepartNom"] = contentEncounter["equipeDepartNom"].replace("'", u"''")
				contentEncounter["equipeDestinationNom"] = contentEncounter["equipeDestinationNom"].replace("'", u"''")
				contentEncounter["equipeDepartVille"] = contentEncounter["equipeDepartVille"].replace("'", u"''")
				contentEncounter["equipeDestinationVille"] = contentEncounter["equipeDestinationVille"].replace("'", u"''")
# 				logging.debug("contentEncounter : %s" %contentEncounter)

	except Exception as e:
		show_exception_traceback()

"""
Function to check final result
send error message to user if one tries to relaunch based on final result
final result flag is set when user tries to play the variation of team number in pools
"""
# def check_final_result(calculatedResult, userId):
def check_final_result(calculatedResult, userId, reportId):
	try:
# 		sql = "select nom from rapport where id=%s"%reportId
		sql = "select nom from parametres where id=%s"%reportId
		reportName = db.fetchone(sql)

		if "params" in calculatedResult:
			if "final" in calculatedResult["params"]:
# 				if results["params"]["final"] == "yes":
				if results["params"]["final"] == "oui":
					
					TEXT = u"Bonjour,\n\n" 
					TEXT += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName
					TEXT += u"Vous ne pouvez lancer le critère de variation du nombre d'équipes par poule qu'une seule fois. " 
					send_email_to_user_failure_with_text(userId, TEXT)
					
		
	except Exception as e:
		show_exception_traceback()

"""
Function to check params for post treatment (variation of team members per pool) round trip and one way match
"""
def check_given_params_post_treatment(calculatedResult, launchType, poolNbr, prohibitionConstraints, typeDistributionConstraints, userId, reportId):
	try:
		errorStatus = False
		
# 		sql = "select nom from rapport where id=%s"%reportId
		sql = "select nom from parametres where id=%s"%reportId
		reportName = db.fetchone(sql)
		
		if ( calculatedResult["typeMatch"] != launchType) or (int(calculatedResult["nombrePoule"]) != int(poolNbr)):
			errorStatus = True
		
# 		logging.debug("calculatedResult : \n%s" %json.dumps(calculatedResult))
		
		# map list of strings to list of ints for prohibition constraints
		prohibitionConstraintsInput = [sorted(list(map(int, prohibitionConstraint))) for prohibitionConstraint in prohibitionConstraints]
		logging.debug("prohibitionConstraintsInput : %s" %prohibitionConstraintsInput)

		# map list of strings to list of ints for type distribution constraints
		typeDistributionConstraintsInput = {}
		for type, members in typeDistributionConstraints.items(): 
			typeDistributionConstraintsInput[type] = sorted(list(map(int, members)))
		logging.debug("typeDistributionConstraintsInput : %s" %typeDistributionConstraintsInput)

		if "params" in calculatedResult:
			# check prohibition constraints
			if "interdictions" in calculatedResult["params"]:
				prohibitionConstraintsSavedUnformatted = calculatedResult["params"]["interdictions"]
				prohibitionConstraintsSaved = []
				for prohibitionNbr, prohibitionConstraintSavedUnformatted in prohibitionConstraintsSavedUnformatted.items():
					prohibitionConstraintsSaved.append(sorted(prohibitionConstraintSavedUnformatted["ids"]))
				if prohibitionConstraintsInput != prohibitionConstraintsSaved: 
					errorStatus = True
# 				logging.debug("prohibitionConstraintsInput == prohibitionConstraintsSaved : %s" %(prohibitionConstraintsInput == prohibitionConstraintsSaved))
			# check type distribution constraints
			if "repartitionsHomogenes" in calculatedResult["params"]:
				typeDistributionConstraintsSavedUnformatted = calculatedResult["params"]["repartitionsHomogenes"]
				typeDistributionConstraintsSaved = {}
				for type, typeDistributionConstraintSavedUnformatted in typeDistributionConstraintsSavedUnformatted.items():
					typeDistributionConstraintsSaved[type] = sorted(typeDistributionConstraintSavedUnformatted["ids"])
				if typeDistributionConstraintsInput != typeDistributionConstraintsSaved: 
					errorStatus = True
# 				logging.debug("typeDistributionConstraintsSaved : %s" %typeDistributionConstraintsSaved)
# 				logging.debug("typeDistributionConstraintsInput : %s" %typeDistributionConstraintsInput)
# 				logging.debug("typeDistributionConstraintsInput == typeDistributionConstraintsSaved : %s" %(typeDistributionConstraintsInput == typeDistributionConstraintsSaved))
				
		
		# send email if errorStatus is true
		if errorStatus:
			TEXT = u"Bonjour,\n\n" 
			TEXT += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName
			TEXT += u"Veuillez utiliser les mêmes parametres que vous avez utilisé précédemment . " 
			send_email_to_user_failure_with_text(userId, TEXT)
		
	except Exception as e:
		show_exception_traceback()


"""
Function to save result into DB
"""
def update_result_to_db(resultId, results):
	try:
		# escape single apostrophe for city names
		# ref scenario
		resultsRef = results["scenarioRef"]
		if resultsRef:
			replace_single_quote_for_result(resultsRef["rencontreDetails"])
# 		logging.debug("resultsRef : %s" %resultsRef)

		# optimal scenario
		resultsOptimalWithoutConstraint = results["scenarioOptimalSansContrainte"]
		if resultsOptimalWithoutConstraint:
			replace_single_quote_for_result(resultsOptimalWithoutConstraint["rencontreDetails"])
		
		resultsOptimalWithConstraint = results["scenarioOptimalAvecContrainte"]
		if resultsOptimalWithConstraint:
			replace_single_quote_for_result(resultsOptimalWithConstraint["rencontreDetails"])
			
		# equitable scenario
		resultsEquitableWithoutConstraint = results["scenarioEquitableSansContrainte"]
		if resultsEquitableWithoutConstraint:
			replace_single_quote_for_result(resultsEquitableWithoutConstraint["rencontreDetails"])
			
		
		resultsEquitableWithConstraint = results["scenarioEquitableAvecContrainte"]
		if resultsEquitableWithConstraint:
			replace_single_quote_for_result(resultsEquitableWithoutConstraint["rencontreDetails"])


		# mark the final result
# 		results["params"]["final"] = "yes"
		results["params"]["final"] = "oui"

# 		sql = """update scenario set details_calcul='%(results)s' where id=%(resultId)s
		sql = """update resultats set details_calcul='%(results)s' where id=%(resultId)s
			"""%{	"resultId": resultId, 
					"results": json.dumps(results),
				}
# 		logging.debug("sql: %s" %sql)
		db.execute(sql)
		db.commit()
		
		return resultId
	except Exception as e:
		show_exception_traceback()

"""
Function to insert params to DB
"""
def test_insert_params_to_db():
	try:
# 		groupId = 68
		groupId = 190
# 		actionType = "allerRetour"
		actionType = "allerSimple"
		name = "rapport_groupe_%s_action_%s"%(groupId, actionType)
		exclusionValue = 0
		creationDate = time.strftime("%Y-%m-%d")
		statut = 1
# 		params = {	"nbrPoule": 3, 
# 					"interdictions": {"1": [8631, 8632]}, 
# 					"repartitionHomogene": {}
# 				}
# 		params = {	"nbrPoule": 3, 
# 					"varEquipeParPoule": 2, 
# 					"interdictions": {}, 
# 					"repartitionHomogene": {"espoir": [8631, 8632]}
# 				}
# 		params = {	"nbrPoule": 3, 
# 					"varEquipeParPoule": 2, 
# 					"interdictions": {}, 
# 					"repartitionHomogene": {}
# 				}
		params = {	"nbrPoule": 4, 
					"varEquipeParPoule": 1, 
					"interdictions": {}, 
					"repartitionHomogene": {}
				}
		
# 		sql = """insert into rapport (nom, id_groupe, type_action, valeur_exclusion , date_creation, params, statut)
		sql = """insert into parametres (nom, id_groupe, type_action, valeur_exclusion , date_creation, params, statut)
				values ( '%(name)s', %(groupId)s, '%(actionType)s', %(exclusionValue)s , '%(creationDate)s', '%(params)s', %(statut)s
					)
			"""%{	"name": name,
					"groupId": groupId,
					"actionType": actionType,
					"exclusionValue": exclusionValue,
					"creationDate": creationDate,
					"params": json.dumps(params),
					"statut": statut,
					
				}
		logging.debug("sql: %s" %sql)
		db.execute(sql)
		db.commit()
		
		sys.exit()


	except Exception as e:
		show_exception_traceback()


"""
Function to update job status
"""
def update_job_status(jobId, status):
	try:
# 		sql = "update rapport set statut=%(status)s where id=%(jobId)s"%{"status": int(status), "jobId": int(jobId)}
		sql = "update parametres set statut=%(status)s where id=%(jobId)s"%{"status": int(status), "jobId": int(jobId)}
		logging.debug("sql: %s" %sql)
		db.execute(sql)
		db.commit()

	except Exception as e:
		show_exception_traceback()




