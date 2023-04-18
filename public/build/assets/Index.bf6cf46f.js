import{i as f,l as G,a0 as I,j as le,o as i,g as u,a as s,b as v,w as r,F as j,H as re,d as t,P as x,c as w,p as m,t as l,m as A,f as a,J as R,a1 as ie,n as _}from"./app.85f64675.js";import{_ as de}from"./Authenticated.0ed74fca.js";import{_ as F}from"./Button.70586e46.js";import ue from"./ChannelOverview.5401378b.js";import ce from"./Form.17dc6b3b.js";import{_ as b,r as me,T,a as pe,b as y}from"./TableData.cd86ed9e.js";import{_ as k}from"./MultiSelect.f958bb22.js";import{_ as S}from"./TableHeadSort.fcfb793f.js";import{r as _e}from"./BackspaceIcon.89322afa.js";import{r as ge}from"./ArrowDownTrayIcon.15de628d.js";import{r as ye}from"./PencilSquareIcon.8748b6d0.js";import"./open-closed.f3e9263d.js";import"./use-resolve-button-type.6d97e4a0.js";import"./RectangleStackIcon.c09510e5.js";import"./Modal.d8e101d2.js";import"./FormInput.aa99a3bb.js";import"./ArrowUturnLeftIcon.bbf601cd.js";import"./CheckCircleIcon.3d1feaa0.js";import"./_plugin-vue_export-helper.cdc0426e.js";const fe=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),he={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ve={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},xe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},be=a(" Vend ID "),ke=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Ce=a(" Channel ID "),Ve=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Te=a(" Serial Num "),Se=a(" Temp >> "),Je=a(" t1-t2 Delta >> "),we=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),Be=a(" Cust ID "),Le=a(" Cust Name "),Ke={key:2},$e=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),De={key:3},Pe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Oe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),Ie={key:4},je=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),Fe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Sensor Status ",-1),Ne=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Door Open ",-1),Ee=a(" Fan Speed << "),Ue={key:5},Me=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),He=a(" Last Visited Day >> "),Ge=a(" Balance Stock(%) << "),Ae=a(" Remaining SKU(%) << "),Re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},qe={class:"mt-3"},Ye={class:"flex space-x-1"},Ze=t("span",null," Search ",-1),ze=t("span",null," Reset ",-1),Qe={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},We=t("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),Xe=t("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),et=[We,Xe],tt=t("span",null," Export Channels Excel ",-1),ot={class:"flex flex-col space-y-1"},nt={class:"text-sm text-gray-700 leading-5"},st={class:"text-sm text-gray-700 leading-5 flex space-x-1"},at=t("span",null,"Showing",-1),lt={class:"font-medium"},rt=t("span",null,"to",-1),it={class:"font-medium"},dt=t("span",null,"of",-1),ut={class:"font-medium"},ct=t("span",null,"results",-1),mt={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},pt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},_t=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Sales (Last 30 days)",-1),gt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},yt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},ft=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Avg per VM (Last 30 days)",-1),ht={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},vt={class:"mt-6 flex flex-col"},xt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},bt={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},kt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ct={class:"bg-gray-100"},Vt={class:"divide-x divide-gray-200"},Tt=a(" # "),St=a(" ID "),Jt=a(" Name "),wt=a(" Temp1(\u2103) "),Bt=t("br",null,null,-1),Lt=a(" \u0394t1-t2 "),Kt=a(" Inventory Status "),$t=t("br",null,null,-1),Dt=a(" (#Channel, Sold, Balance/Capacity) "),Pt=a(" Errors "),Ot=a(" Balance Stock "),It=a(" Remaining SKU# "),jt=a(" $ Sales (qty)"),Ft=t("br",null,null,-1),Nt=a(" Today "),Et=t("br",null,null,-1),Ut=a(" Y'day"),Mt=t("br",null,null,-1),Ht=a(" Last7d "),Gt=t("br",null,null,-1),At=a(" Last30d "),Rt=a(" Status "),qt=a(" Last Visited "),Yt=a(" Temp2 "),Zt=t("br",null,null,-1),zt=a(" (Evap)"),Qt=t("br",null,null,-1),Wt=a(" \u2103 "),Xt=a(" Postcode "),eo=a(" Firmware Ver "),to=a(" Serial Num "),oo={class:"bg-white"},no={key:0},so={key:0},ao=["href"],lo=t("br",null,null,-1),ro={key:1},io=t("br",null,null,-1),uo={key:1},co={class:"flex flex-col items-center"},mo=["onClick"],po={class:"mt-1"},_o=["onClick"],go={class:"text-blue-600"},yo={class:"flex flex-col"},fo={class:"font-bold"},ho=t("br",null,null,-1),vo=t("br",null,null,-1),xo=t("br",null,null,-1),bo=t("br",null,null,-1),ko=t("br",null,null,-1),Co={class:"flex flex-col space-y-1"},Vo={class:"flex flex-col"},To={class:"font-bold"},So={key:0},Jo={class:"flex flex-col"},wo=t("span",{class:"font-bold"}," Drop Sensor ",-1),Bo={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},Lo={class:"flex flex-col"},Ko=t("span",{class:"font-bold"}," Fan Speed ",-1),$o={class:"flex flex-col"},Do=t("span",{class:"font-bold"}," Door ",-1),Po={class:"flex flex-col"},Oo=t("span",{class:"font-bold"}," Coin ",-1),Io=t("br",null,null,-1),jo={class:"flex flex-col items-center space-y-1"},Fo=["onClick"],No=["onClick"],Eo=["onClick"],Uo={key:0,class:"text-blue-600"},Mo=t("br",null,null,-1),Ho={key:0},Go={class:"flex justify-center space-x-1"},Ao=t("span",null," Edit ",-1),Ro={key:0},qo=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Yo=[qo],gn={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,operatorOptions:Object,totals:[Array,Object],vends:Object,vendChannelErrors:Object},setup(d){const B=d,n=f({codes:"",channel_codes:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],operator:"",is_binded_customer:"",tempHigherThan:"",tempDeltaHigherThan:"",vend_channel_error_id:"",lastVisitedGreaterThan:"",is_online:"",is_sensor:"",is_door_open:"",fanSpeedLowerThan:"",balanceStockLessThan:"",remainingSkuLessThan:"",sortKey:"",sortBy:!1,numberPerPage:""}),J=f([]),q=f([]),Y=f([]),N=f([]),E=f([]),L=f(!1),U=f([]),M=f([]),K=f(!1),$=f(!1),W=f(""),D=f(),H=f([]),X=G().props.value.auth.operatorRole;G().props.value.auth.roles;const C=G().props.value.auth.permissions,Z=f(I().format("HH:mm:ss"));le(()=>{H.value=[{id:"errors_only",desc:"Errors Only"},...B.vendChannelErrors.data],U.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.vend_channel_error_id=H.value[0],n.value.numberPerPage=U.value[0],q.value=B.categories.data.map(c=>({id:c.id,name:c.name})),Y.value=B.categoryGroups.data.map(c=>({id:c.id,name:c.name})),J.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],E.value=[{id:"all",value:"All"},{id:"true",value:"Enabled"},{id:"false",value:"Disabled"}],N.value=[{id:"all",value:"All"},{id:"open",value:"Open"},{id:"close",value:"Close"}],M.value=[{id:"all",full_name:"All"},...B.operatorOptions.data.map(c=>({id:c.id,full_name:c.full_name}))],n.value.is_door_open=N.value[0],n.value.is_online=J.value[0],n.value.is_sensor=E.value[0],n.value.is_binded_customer=X.value?J.value[0]:J.value[1],n.value.operator=M.value[0]});function ee(c){D.value=c,K.value=!0}function te(){K.value=!1}function oe(c){D.value=c,$.value=!0}function ne(){$.value=!1}function h(){R.Inertia.get("/vends",{...n.value,categories:n.value.categories.map(c=>c.id),categoryGroups:n.value.categoryGroups.map(c=>c.id),errors:n.value.errors.map(c=>c.id),operator_id:n.value.operator.id,is_binded_customer:n.value.is_binded_customer.id,is_door_open:n.value.is_door_open.id,is_online:n.value.is_online.id,is_sensor:n.value.is_sensor.id,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0,onFinish:c=>{Z.value=I().format("HH:mm:ss")}})}function P(c,o){R.Inertia.get("/vends/"+c+"/temp/"+o)}function se(){R.Inertia.get("/vends")}function V(c){n.value.sortKey=c,n.value.sortBy=!n.value.sortBy,h()}function ae(){L.value=!0,ie({method:"get",url:"/vends/channels/excel",params:{...n.value,categories:n.value.categories.map(c=>c.id),categoryGroups:n.value.categoryGroups.map(c=>c.id),errors:n.value.errors.map(c=>c.id),operator_id:n.value.operator.id,is_binded_customer:n.value.is_binded_customer.id,is_door_open:n.value.is_door_open.id,is_online:n.value.is_online.id,is_sensor:n.value.is_sensor.id},responseType:"blob"}).then(c=>{fileDownload(c.data,"Vending_Channels_"+I().format("YYMMDDhhmmss")+".xlsx")}).catch(c=>{console.log(c)}).finally(()=>{L.value=!1})}return(c,o)=>(i(),u(j,null,[s(v(re),{title:"Vending Machines"}),s(de,null,{header:r(()=>[fe]),default:r(()=>{var z,Q;return[t("div",he,[t("div",ve,[t("div",xe,[s(b,{placeholderStr:"Vend ID",modelValue:n.value.codes,"onUpdate:modelValue":o[0]||(o[0]=e=>n.value.codes=e),onKeyup:o[1]||(o[1]=x(e=>h(),["enter"]))},{default:r(()=>[be,ke]),_:1},8,["modelValue"]),s(b,{placeholderStr:"Channel ID",modelValue:n.value.channel_codes,"onUpdate:modelValue":o[2]||(o[2]=e=>n.value.channel_codes=e),onKeyup:o[3]||(o[3]=x(e=>h(),["enter"]))},{default:r(()=>[Ce,Ve]),_:1},8,["modelValue"]),s(b,{placeholderStr:"Serial Num",modelValue:n.value.serialNum,"onUpdate:modelValue":o[4]||(o[4]=e=>n.value.serialNum=e),onKeyup:o[5]||(o[5]=x(e=>h(),["enter"]))},{default:r(()=>[Te]),_:1},8,["modelValue"]),s(b,{placeholderStr:"Number",modelValue:n.value.tempHigherThan,"onUpdate:modelValue":o[6]||(o[6]=e=>n.value.tempHigherThan=e),onKeyup:o[7]||(o[7]=x(e=>h(),["enter"]))},{default:r(()=>[Se]),_:1},8,["modelValue"]),s(b,{placeholderStr:"Number",modelValue:n.value.tempDeltaHigherThan,"onUpdate:modelValue":o[8]||(o[8]=e=>n.value.tempDeltaHigherThan=e),onKeyup:o[9]||(o[9]=x(e=>h(),["enter"]))},{default:r(()=>[Je]),_:1},8,["modelValue"]),t("div",null,[we,s(k,{modelValue:n.value.errors,"onUpdate:modelValue":o[10]||(o[10]=e=>n.value.errors=e),options:H.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),v(C).includes("admin-access vends")?(i(),w(b,{key:0,placeholderStr:"Cust ID",modelValue:n.value.customer_code,"onUpdate:modelValue":o[11]||(o[11]=e=>n.value.customer_code=e),onKeyup:o[12]||(o[12]=x(e=>h(),["enter"]))},{default:r(()=>[Be]),_:1},8,["modelValue"])):m("",!0),v(C).includes("admin-access vends")?(i(),w(b,{key:1,placeholderStr:"Cust Name",modelValue:n.value.customer_name,"onUpdate:modelValue":o[13]||(o[13]=e=>n.value.customer_name=e),onKeyup:o[14]||(o[14]=x(e=>h(),["enter"]))},{default:r(()=>[Le]),_:1},8,["modelValue"])):m("",!0),v(C).includes("admin-access vends")?(i(),u("div",Ke,[$e,s(k,{modelValue:n.value.categories,"onUpdate:modelValue":o[15]||(o[15]=e=>n.value.categories=e),options:q.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),v(C).includes("admin-access vends")?(i(),u("div",De,[Pe,s(k,{modelValue:n.value.categoryGroups,"onUpdate:modelValue":o[16]||(o[16]=e=>n.value.categoryGroups=e),options:Y.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[Oe,s(k,{modelValue:n.value.is_online,"onUpdate:modelValue":o[17]||(o[17]=e=>n.value.is_online=e),options:J.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),v(C).includes("admin-access vends")?(i(),u("div",Ie,[je,s(k,{modelValue:n.value.is_binded_customer,"onUpdate:modelValue":o[18]||(o[18]=e=>n.value.is_binded_customer=e),options:J.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[Fe,s(k,{modelValue:n.value.is_sensor,"onUpdate:modelValue":o[19]||(o[19]=e=>n.value.is_sensor=e),options:E.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[Ne,s(k,{modelValue:n.value.is_door_open,"onUpdate:modelValue":o[20]||(o[20]=e=>n.value.is_door_open=e),options:N.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),s(b,{placeholderStr:"Fan Speed",modelValue:n.value.fanSpeedLowerThan,"onUpdate:modelValue":o[21]||(o[21]=e=>n.value.fanSpeedLowerThan=e),onKeyup:o[22]||(o[22]=x(e=>h(),["enter"]))},{default:r(()=>[Ee]),_:1},8,["modelValue"]),v(C).includes("admin-access vends")?(i(),u("div",Ue,[Me,s(k,{modelValue:n.value.operator,"onUpdate:modelValue":o[23]||(o[23]=e=>n.value.operator=e),options:M.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),s(b,{placeholderStr:"How many Day(s)",modelValue:n.value.lastVisitedGreaterThan,"onUpdate:modelValue":o[24]||(o[24]=e=>n.value.lastVisitedGreaterThan=e),onKeyup:o[25]||(o[25]=x(e=>h(),["enter"]))},{default:r(()=>[He]),_:1},8,["modelValue"]),s(b,{placeholderStr:"Balance Stock Less Than",modelValue:n.value.balanceStockLessThan,"onUpdate:modelValue":o[26]||(o[26]=e=>n.value.balanceStockLessThan=e),onKeyup:o[27]||(o[27]=x(e=>h(),["enter"]))},{default:r(()=>[Ge]),_:1},8,["modelValue"]),s(b,{placeholderStr:"Remaining SKU Less Than",modelValue:n.value.remainingSkuLessThan,"onUpdate:modelValue":o[28]||(o[28]=e=>n.value.remainingSkuLessThan=e),onKeyup:o[29]||(o[29]=x(e=>h(),["enter"]))},{default:r(()=>[Ae]),_:1},8,["modelValue"])]),t("div",Re,[t("div",qe,[t("div",Ye,[s(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[30]||(o[30]=e=>h())},{default:r(()=>[s(v(me),{class:"h-4 w-4","aria-hidden":"true"}),Ze]),_:1}),s(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[31]||(o[31]=e=>se())},{default:r(()=>[s(v(_e),{class:"h-4 w-4","aria-hidden":"true"}),ze]),_:1}),s(F,{class:"inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[32]||(o[32]=e=>ae())},{default:r(()=>[L.value?m("",!0):(i(),w(v(ge),{key:0,class:"h-4 w-4","aria-hidden":"true"})),L.value?(i(),u("svg",Qe,et)):m("",!0),tt]),_:1})])]),t("div",ot,[t("span",nt,[t("p",null,"Last loaded: "+l(Z.value),1)]),t("p",st,[at,t("span",lt,l((z=d.vends.meta.from)!=null?z:0),1),rt,t("span",it,l((Q=d.vends.meta.to)!=null?Q:0),1),dt,t("span",ut,l(d.vends.meta.total),1),ct]),s(k,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":o[33]||(o[33]=e=>n.value.numberPerPage=e),options:U.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:h},null,8,["modelValue","options"])])]),t("dl",mt,[t("div",pt,[_t,t("dd",gt,l(d.totals.thirtyDays.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),t("div",yt,[ft,t("dd",ht,l((d.totals.thirtyDays/d.vends.meta.to?d.totals.thirtyDays/d.vends.meta.to:0).toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)])])]),t("div",vt,[t("div",xt,[t("div",bt,[t("table",kt,[t("thead",Ct,[t("tr",Vt,[s(T,null,{default:r(()=>[Tt]),_:1}),s(S,{modelName:"vends.code",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[34]||(o[34]=e=>V("vends.code"))},{default:r(()=>[St]),_:1},8,["sortKey","sortBy"]),s(T,null,{default:r(()=>[Jt]),_:1}),s(S,{modelName:"temp",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[35]||(o[35]=e=>V("temp"))},{default:r(()=>[wt,Bt,Lt]),_:1},8,["sortKey","sortBy"]),s(T,null,{default:r(()=>[Kt,$t,Dt]),_:1}),s(T,null,{default:r(()=>[Pt]),_:1}),s(S,{modelName:"vend_channel_totals_json->balancePercent",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[36]||(o[36]=e=>V("vend_channel_totals_json->balancePercent"))},{default:r(()=>[Ot]),_:1},8,["sortKey","sortBy"]),s(S,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[37]||(o[37]=e=>V("vend_channel_totals_json->outOfStockSkuPercent"))},{default:r(()=>[It]),_:1},8,["sortKey","sortBy"]),s(S,{modelName:"vend_transaction_totals_json->thirty_days_amount",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[38]||(o[38]=e=>V("vend_transaction_totals_json->thirty_days_amount"))},{default:r(()=>[jt,Ft,Nt,Et,Ut,Mt,Ht,Gt,At]),_:1},8,["sortKey","sortBy"]),s(T,null,{default:r(()=>[Rt]),_:1}),s(S,{modelName:"last_invoice_date",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[39]||(o[39]=e=>V("last_invoice_date"))},{default:r(()=>[qt]),_:1},8,["sortKey","sortBy"]),s(S,{modelName:"parameter_json->t2",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[40]||(o[40]=e=>V("parameter_json->t2"))},{default:r(()=>[Yt,Zt,zt,Qt,Wt]),_:1},8,["sortKey","sortBy"]),s(S,{modelName:"postcode",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[41]||(o[41]=e=>V("postcode"))},{default:r(()=>[Xt]),_:1},8,["sortKey","sortBy"]),s(T,null,{default:r(()=>[eo]),_:1}),s(T,null,{default:r(()=>[to]),_:1}),s(T)])]),t("tbody",oo,[(i(!0),u(j,null,A(d.vends.data,(e,g)=>(i(),u("tr",{key:g,class:"divide-x divide-gray-200"},[s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(d.vends.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.latestVendBinding&&e.latestVendBinding.customer?(i(),u("span",no,[v(C).includes("admin-access vends")?(i(),u("span",so,[t("a",{class:"text-blue-700",target:"_blank",href:"//admin.happyice.com.sg/person/vend-code/"+e.code},[a(l(e.latestVendBinding.customer.code)+" ",1),lo,a(" "+l(e.latestVendBinding.customer.name),1)],8,ao)])):(i(),u("span",ro,[a(l(e.latestVendBinding.customer.code)+" ",1),io,a(" "+l(e.latestVendBinding.customer.name),1)]))])):(i(),u("span",uo,l(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",co,[t("button",{type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>P(e.id,1)},l(e.is_temp_error?"Error":e.temp),11,mo),t("span",po,l(e.temp_updated_at),1),e.parameterJson&&e.parameterJson.t2&&e.parameterJson.t2!=d.constTempError&&!e.is_temp_error?(i(),u("span",{key:0,class:_(["mt-1",(e.temp-e.parameterJson.t2/10).toFixed(1)>=4?"text-red-700":"text-green-700"])},l((e.temp-e.parameterJson.t2/10).toFixed(1)),3)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.vendChannelsJson?(i(),u("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:p=>ee(e)},[(i(!0),u(j,null,A(e.vendChannelsJson,(p,O)=>(i(),u("li",{class:_(["quick-look",[O>0&&String(p.code)[0]!==String(e.vendChannelsJson[O-1].code)[0]?"col-start-1":""]])},[t("span",{class:_([O>0&&String(p.code)[0]!==String(e.vendChannelsJson[O-1].code)[0]?"border-t-4 pt-1":""])},[t("span",null," #"+l(p.code)+", ",1),t("span",go,l(p.capacity-p.qty)+", ",1),t("span",{class:_([p.qty<=2?"text-red-700":"text-green-700"])},l(p.qty)+"/"+l(p.capacity),3)],2)],2))),256))],8,_o)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[(i(!0),u(j,null,A(e.vendChannelErrorLogsJson,p=>(i(),u("span",{class:_(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[p.vendChannelError?p.vendChannelError.code==4||p.vendChannelError.code==5||p.vendChannelError.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800":p.vend_channel.code==4||p.vend_channel.code==5||p.vend_channel.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800"]])},[t("div",yo,[t("div",null,[a(" #"+l(p.vendChannel?p.vendChannel.code:p.vend_channel.code)+", ",1),t("span",fo," ("+l(p.vendChannelError?p.vendChannelError.code:p.vend_channel_error.code)+") ",1)]),t("div",null,l(p.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),u("span",{key:0,class:_([e.vendChannelTotalsJson.balancePercent<=15?"text-red-700":e.vendChannelTotalsJson.balancePercent>40?"text-green-700":"text-blue-700"])},[a(l(e.vendChannelTotalsJson.qty)+"/ "+l(e.vendChannelTotalsJson.capacity)+" ",1),ho,a(" ("+l(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),u("span",{key:0,class:_([100-e.vendChannelTotalsJson.outOfStockSkuPercent<=25?"text-red-700":100-e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-green-700":"text-blue-700"])},[a(l(e.vendChannelTotalsJson.count-e.vendChannelTotalsJson.outOfStockSku)+"/ "+l(e.vendChannelTotalsJson.count)+" ",1),vo,a(" ("+l(100-e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>["today_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:0,class:_([e.vendTransactionTotalsJson.today_amount/100>=30?"text-green-700":"text-red-700"])},l((e.vendTransactionTotalsJson.today_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.today_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",3)):m("",!0),"yesterday_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:1,class:_([e.vendTransactionTotalsJson.yesterday_amount/100>=30?"text-green-700":"text-red-700"])},[xo,a(" "+l((e.vendTransactionTotalsJson.yesterday_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.yesterday_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),"seven_days_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:2,class:_([e.vendTransactionTotalsJson.seven_days_amount/100>200?"text-green-700":"text-red-700"])},[bo,a(" "+l((e.vendTransactionTotalsJson.seven_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+l(e.vendTransactionTotalsJson.seven_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),"thirty_days_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:3,class:_([e.vendTransactionTotalsJson.thirty_days_amount/100>1e3?"text-green-700":"text-red-700"])},[ko,a(" "+l((e.vendTransactionTotalsJson.thirty_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+l(e.vendTransactionTotalsJson.thirty_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Co,[t("div",{class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",Vo,[t("span",To,l(e.is_online?"Online":"Offline"),1),e.last_updated_at?(i(),u("span",So,l(e.last_updated_at),1)):m("",!0)])],2),e.parameterJson?(i(),u("div",{key:0,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor%2==0?"bg-red-200":"bg-green-200"]])},[t("div",Jo,[wo,t("span",null,l(e.parameterJson.Sensor%2==0?"Disabled":"Enabled"),1)])],2)):m("",!0),e.parameterJson&&"fan"in e.parameterJson?(i(),u("div",Bo,[t("div",Lo,[Ko,t("span",null,l(e.parameterJson.fan),1)])])):m("",!0),e.parameterJson&&e.parameterJson.door?(i(),u("div",{key:2,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",$o,[Do,t("span",null,l(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):m("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(i(),u("div",{key:3,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",Po,[Oo,t("span",null,l((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("span",null,[a(l(e.last_invoice_date)+" ",1),Io,a(" "+l(e.last_invoice_diff),1)])]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",jo,[e.parameterJson&&"t2"in e.parameterJson?(i(),u("button",{key:0,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>P(e.id,2)},l(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10)+"(t2) ",11,Fo)):m("",!0),e.parameterJson&&e.parameterJson.t3&&e.parameterJson.t3!=d.constTempError?(i(),u("button",{key:1,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t3==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>P(e.id,3)},l(e.parameterJson.t3==d.constTempError?"Error":e.parameterJson.t3/10)+"(t3) ",11,No)):m("",!0),e.parameterJson&&e.parameterJson.t4&&e.parameterJson.t4!=d.constTempError?(i(),u("button",{key:2,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t4==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>P(e.id,4)},l(e.parameterJson.t4==d.constTempError?"Error":e.parameterJson.t4/10)+"(t4) ",11,Eo)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?e.latestVendBinding.customer.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.parameterJson&&e.parameterJson.Ver?e.parameterJson.Ver.toString(16):null)+" ",1),e.apkVerJson&&"apkver"in e.apkVerJson?(i(),u("span",Uo,[Mo,a("Apk: "+l(e.apkVerJson.apkver)+" ",1),e.apkVerJson&&"buildtime"in e.apkVerJson?(i(),u("span",Ho,l(v(I)(new Date(e.apkVerJson.buildtime)).format("YYMMDD HH:mm:ss")),1)):m("",!0)])):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Go,[s(F,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:p=>oe(e)},{default:r(()=>[s(v(ye),{class:"w-4 h-4"}),Ao]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?m("",!0):(i(),u("tr",Ro,Yo))])])]),d.vends.data.length?(i(),w(pe,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):m("",!0)])])]),K.value?(i(),w(ue,{key:0,vend:D.value,showModal:K.value,onModalClose:te},null,8,["vend","showModal"])):m("",!0),$.value?(i(),w(ce,{key:1,vend:D.value,type:W.value,showModal:$.value,permissions:v(C),onModalClose:ne},null,8,["vend","type","showModal","permissions"])):m("",!0)]}),_:1})],64))}};export{gn as default};