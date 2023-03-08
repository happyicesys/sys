import{_ as ae}from"./Authenticated.58009be2.js";import{_ as $}from"./Button.4929fb8c.js";import le from"./ChannelOverview.5de5af41.js";import re from"./Form.e928c30a.js";import{_ as v,r as ie,T as k,a as de,b as h}from"./TableData.e157e6dc.js";import{_ as x}from"./MultiSelect.fc551de0.js";import{_ as V}from"./TableHeadSort.19bc8ecc.js";import{i as f,l as H,V as F,j as ue,o as i,g as u,a as n,b as y,w as r,F as j,H as ce,d as t,c as S,p as m,t as a,m as A,f as l,J as G,W as me,n as p}from"./app.d2aee40d.js";import{r as _e}from"./BackspaceIcon.8bd66dde.js";import{r as pe}from"./ArrowDownTrayIcon.13d0e025.js";import{r as ge}from"./PencilSquareIcon.9f53b5e6.js";import"./open-closed.9b4863b8.js";import"./use-resolve-button-type.b75ef9de.js";import"./RectangleStackIcon.e939b5d1.js";import"./Modal.750380bd.js";import"./FormInput.7b125a96.js";import"./ArrowUturnLeftIcon.e4bba664.js";import"./CheckCircleIcon.b54f08dd.js";import"./_plugin-vue_export-helper.cdc0426e.js";const he=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),fe={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ye={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},xe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},be=l(" Vend ID "),ve=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),ke=l(" Channel ID "),Ce=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Ve=l(" Serial Num "),Te=l(" Temp >> "),Se=l(" t1-t2 Delta >> "),Je=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),we=l(" Cust ID "),Be=l(" Cust Name "),De={key:2},Le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),Pe={key:3},Oe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),$e=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),Fe={key:4},je=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),Ie=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Sensor Status ",-1),Ee=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Door Open ",-1),Ne=l(" Fan Speed << "),Ke={key:5},Me=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),Ue={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},He={class:"mt-3"},Ae={class:"flex space-x-1"},Ge=t("span",null," Search ",-1),qe=t("span",null," Reset ",-1),Ye={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Re=t("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),Ze=t("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),ze=[Re,Ze],We=t("span",null," Export Channels Excel ",-1),Qe={class:"flex flex-col space-y-1"},Xe={class:"text-sm text-gray-700 leading-5"},et={class:"text-sm text-gray-700 leading-5 flex space-x-1"},tt=t("span",null,"Showing",-1),ot={class:"font-medium"},st=t("span",null,"to",-1),nt={class:"font-medium"},at=t("span",null,"of",-1),lt={class:"font-medium"},rt=t("span",null,"results",-1),it={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},dt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},ut=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Sales (Last 30 days)",-1),ct={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},mt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},_t=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Count (Last 30 days)",-1),pt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},gt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},ht=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Avg per VM (Last 30 days)",-1),ft={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},yt={class:"mt-6 flex flex-col"},xt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},bt={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},vt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},kt={class:"bg-gray-100"},Ct={class:"divide-x divide-gray-200"},Vt=l(" # "),Tt=l(" ID "),St=l(" Name "),Jt=l(" Temp1(\u2103) "),wt=t("br",null,null,-1),Bt=l(" \u0394t1-t2 "),Dt=l(" Inventory Status "),Lt=t("br",null,null,-1),Pt=l(" (#Channel, Sales, Balance/Capacity) "),Ot=l(" Errors "),$t=l(" Balance Stock "),Ft=l(" Out of Stock SKU "),jt=l(" $ Sales (qty)"),It=t("br",null,null,-1),Et=l(" Today "),Nt=t("br",null,null,-1),Kt=l(" Y'day"),Mt=t("br",null,null,-1),Ut=l(" Last7d "),Ht=t("br",null,null,-1),At=l(" Last30d "),Gt=l(" Status "),qt=l(" Temp2 "),Yt=t("br",null,null,-1),Rt=l(" (Evap)"),Zt=t("br",null,null,-1),zt=l(" \u2103 "),Wt=l(" Postcode "),Qt=l(" Firmware Ver "),Xt=l(" Serial Num "),eo={class:"bg-white"},to={key:0},oo={key:0},so=["href"],no=t("br",null,null,-1),ao={key:1},lo=t("br",null,null,-1),ro={key:1},io={class:"flex flex-col items-center"},uo=["onClick"],co={class:"mt-1"},mo=["onClick"],_o={class:"text-blue-600"},po={class:"flex flex-col"},go={class:"font-bold"},ho=t("br",null,null,-1),fo=t("br",null,null,-1),yo=t("br",null,null,-1),xo=t("br",null,null,-1),bo=t("br",null,null,-1),vo={class:"flex flex-col space-y-1"},ko={class:"flex flex-col"},Co={class:"font-bold"},Vo={key:0},To={class:"flex flex-col"},So=t("span",{class:"font-bold"}," Drop Sensor ",-1),Jo={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},wo={class:"flex flex-col"},Bo=t("span",{class:"font-bold"}," Fan Speed ",-1),Do={class:"flex flex-col"},Lo=t("span",{class:"font-bold"}," Door ",-1),Po={class:"flex flex-col"},Oo=t("span",{class:"font-bold"}," Coin ",-1),$o={class:"flex flex-col items-center space-y-1"},Fo=["onClick"],jo=["onClick"],Io=["onClick"],Eo={key:0,class:"text-blue-600"},No=t("br",null,null,-1),Ko={key:0},Mo={class:"flex justify-center space-x-1"},Uo=t("span",null," Edit ",-1),Ho={key:0},Ao=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Go=[Ao],cs={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,operatorOptions:Object,totals:[Array,Object],vends:Object,vendChannelErrors:Object},setup(d){const J=d,o=f({codes:"",channel_codes:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],operator:"",is_binded_customer:"",tempHigherThan:"",tempDeltaHigherThan:"",vend_channel_error_id:"",is_online:"",is_sensor:"",is_door_open:"",fanSpeedLowerThan:"",sortKey:"",sortBy:!1,numberPerPage:""}),T=f([]),q=f([]),Y=f([]),I=f([]),E=f([]),w=f(!1),N=f([]),K=f([]),B=f(!1),D=f(!1),W=f(""),L=f(),M=f([]),Q=H().props.value.auth.operatorRole;H().props.value.auth.roles;const b=H().props.value.auth.permissions,R=f(F().format("HH:mm:ss"));ue(()=>{M.value=[{id:"errors_only",desc:"Errors Only"},...J.vendChannelErrors.data],N.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=M.value[0],o.value.numberPerPage=N.value[0],q.value=J.categories.data.map(c=>({id:c.id,name:c.name})),Y.value=J.categoryGroups.data.map(c=>({id:c.id,name:c.name})),T.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],E.value=[{id:"all",value:"All"},{id:"true",value:"Enabled"},{id:"false",value:"Disabled"}],I.value=[{id:"all",value:"All"},{id:"open",value:"Open"},{id:"close",value:"Close"}],K.value=[{id:"all",full_name:"All"},...J.operatorOptions.data.map(c=>({id:c.id,full_name:c.full_name}))],o.value.is_door_open=I.value[0],o.value.is_online=T.value[0],o.value.is_sensor=E.value[0],o.value.is_binded_customer=Q.value?T.value[0]:T.value[1],o.value.operator=K.value[0]});function X(c){L.value=c,B.value=!0}function ee(){B.value=!1}function te(c){L.value=c,D.value=!0}function oe(){D.value=!1}function U(){G.Inertia.get("/vends",{...o.value,categories:o.value.categories.map(c=>c.id),categoryGroups:o.value.categoryGroups.map(c=>c.id),errors:o.value.errors.map(c=>c.id),operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0,onFinish:c=>{R.value=F().format("HH:mm:ss")}})}function P(c,s){G.Inertia.get("/vends/"+c+"/temp/"+s)}function se(){G.Inertia.get("/vends")}function C(c){o.value.sortKey=c,o.value.sortBy=!o.value.sortBy,U()}function ne(){w.value=!0,me({method:"get",url:"/vends/channels/excel",params:{...o.value,categories:o.value.categories.map(c=>c.id),categoryGroups:o.value.categoryGroups.map(c=>c.id),errors:o.value.errors.map(c=>c.id),operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id},responseType:"blob"}).then(c=>{fileDownload(c.data,"Vending_Channels_"+F().format("YYMMDDhhmmss")+".xlsx")}).catch(c=>{console.log(c)}).finally(()=>{w.value=!1})}return(c,s)=>(i(),u(j,null,[n(y(ce),{title:"Vending Machines"}),n(ae,null,{header:r(()=>[he]),default:r(()=>{var Z,z;return[t("div",fe,[t("div",ye,[t("div",xe,[n(v,{placeholderStr:"Vend ID",modelValue:o.value.codes,"onUpdate:modelValue":s[0]||(s[0]=e=>o.value.codes=e)},{default:r(()=>[be,ve]),_:1},8,["modelValue"]),n(v,{placeholderStr:"Channel ID",modelValue:o.value.channel_codes,"onUpdate:modelValue":s[1]||(s[1]=e=>o.value.channel_codes=e)},{default:r(()=>[ke,Ce]),_:1},8,["modelValue"]),n(v,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":s[2]||(s[2]=e=>o.value.serialNum=e)},{default:r(()=>[Ve]),_:1},8,["modelValue"]),n(v,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":s[3]||(s[3]=e=>o.value.tempHigherThan=e)},{default:r(()=>[Te]),_:1},8,["modelValue"]),n(v,{placeholderStr:"Number",modelValue:o.value.tempDeltaHigherThan,"onUpdate:modelValue":s[4]||(s[4]=e=>o.value.tempDeltaHigherThan=e)},{default:r(()=>[Se]),_:1},8,["modelValue"]),t("div",null,[Je,n(x,{modelValue:o.value.errors,"onUpdate:modelValue":s[5]||(s[5]=e=>o.value.errors=e),options:M.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),y(b).includes("admin-access vends")?(i(),S(v,{key:0,placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":s[6]||(s[6]=e=>o.value.customer_code=e)},{default:r(()=>[we]),_:1},8,["modelValue"])):m("",!0),y(b).includes("admin-access vends")?(i(),S(v,{key:1,placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":s[7]||(s[7]=e=>o.value.customer_name=e)},{default:r(()=>[Be]),_:1},8,["modelValue"])):m("",!0),y(b).includes("admin-access vends")?(i(),u("div",De,[Le,n(x,{modelValue:o.value.categories,"onUpdate:modelValue":s[8]||(s[8]=e=>o.value.categories=e),options:q.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),y(b).includes("admin-access vends")?(i(),u("div",Pe,[Oe,n(x,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":s[9]||(s[9]=e=>o.value.categoryGroups=e),options:Y.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[$e,n(x,{modelValue:o.value.is_online,"onUpdate:modelValue":s[10]||(s[10]=e=>o.value.is_online=e),options:T.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),y(b).includes("admin-access vends")?(i(),u("div",Fe,[je,n(x,{modelValue:o.value.is_binded_customer,"onUpdate:modelValue":s[11]||(s[11]=e=>o.value.is_binded_customer=e),options:T.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[Ie,n(x,{modelValue:o.value.is_sensor,"onUpdate:modelValue":s[12]||(s[12]=e=>o.value.is_sensor=e),options:E.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[Ee,n(x,{modelValue:o.value.is_door_open,"onUpdate:modelValue":s[13]||(s[13]=e=>o.value.is_door_open=e),options:I.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),n(v,{placeholderStr:"Fan Speed",modelValue:o.value.fanSpeedLowerThan,"onUpdate:modelValue":s[14]||(s[14]=e=>o.value.fanSpeedLowerThan=e)},{default:r(()=>[Ne]),_:1},8,["modelValue"]),y(b).includes("admin-access vends")?(i(),u("div",Ke,[Me,n(x,{modelValue:o.value.operator,"onUpdate:modelValue":s[15]||(s[15]=e=>o.value.operator=e),options:K.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0)]),t("div",Ue,[t("div",He,[t("div",Ae,[n($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[16]||(s[16]=e=>U())},{default:r(()=>[n(y(ie),{class:"h-4 w-4","aria-hidden":"true"}),Ge]),_:1}),n($,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[17]||(s[17]=e=>se())},{default:r(()=>[n(y(_e),{class:"h-4 w-4","aria-hidden":"true"}),qe]),_:1}),n($,{class:"inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[18]||(s[18]=e=>ne())},{default:r(()=>[w.value?m("",!0):(i(),S(y(pe),{key:0,class:"h-4 w-4","aria-hidden":"true"})),w.value?(i(),u("svg",Ye,ze)):m("",!0),We]),_:1})])]),t("div",Qe,[t("span",Xe,[t("p",null,"Last loaded: "+a(R.value),1)]),t("p",et,[tt,t("span",ot,a((Z=d.vends.meta.from)!=null?Z:0),1),st,t("span",nt,a((z=d.vends.meta.to)!=null?z:0),1),at,t("span",lt,a(d.vends.meta.total),1),rt]),n(x,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[19]||(s[19]=e=>o.value.numberPerPage=e),options:N.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:U},null,8,["modelValue","options"])])]),t("dl",it,[t("div",dt,[ut,t("dd",ct,a(d.totals.thirtyDays.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),t("div",mt,[_t,t("dd",pt,a(d.totals.thirtyDaysCount.toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)]),t("div",gt,[ht,t("dd",ft,a((d.totals.thirtyDays/d.vends.meta.total).toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)])])]),t("div",yt,[t("div",xt,[t("div",bt,[t("table",vt,[t("thead",kt,[t("tr",Ct,[n(k,null,{default:r(()=>[Vt]),_:1}),n(V,{modelName:"vends.code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[20]||(s[20]=e=>C("vends.code"))},{default:r(()=>[Tt]),_:1},8,["sortKey","sortBy"]),n(k,null,{default:r(()=>[St]),_:1}),n(V,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[21]||(s[21]=e=>C("temp"))},{default:r(()=>[Jt,wt,Bt]),_:1},8,["sortKey","sortBy"]),n(k,null,{default:r(()=>[Dt,Lt,Pt]),_:1}),n(k,null,{default:r(()=>[Ot]),_:1}),n(V,{modelName:"vend_channel_totals_json->balancePercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[22]||(s[22]=e=>C("vend_channel_totals_json->balancePercent"))},{default:r(()=>[$t]),_:1},8,["sortKey","sortBy"]),n(V,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[23]||(s[23]=e=>C("vend_channel_totals_json->outOfStockSkuPercent"))},{default:r(()=>[Ft]),_:1},8,["sortKey","sortBy"]),n(V,{modelName:"vend_transaction_totals_json->thirty_days_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[24]||(s[24]=e=>C("vend_transaction_totals_json->thirty_days_amount"))},{default:r(()=>[jt,It,Et,Nt,Kt,Mt,Ut,Ht,At]),_:1},8,["sortKey","sortBy"]),n(k,null,{default:r(()=>[Gt]),_:1}),n(V,{modelName:"parameter_json->t2",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[25]||(s[25]=e=>C("parameter_json->t2"))},{default:r(()=>[qt,Yt,Rt,Zt,zt]),_:1},8,["sortKey","sortBy"]),n(V,{modelName:"postcode",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[26]||(s[26]=e=>C("postcode"))},{default:r(()=>[Wt]),_:1},8,["sortKey","sortBy"]),n(k,null,{default:r(()=>[Qt]),_:1}),n(k,null,{default:r(()=>[Xt]),_:1}),n(k)])]),t("tbody",eo,[(i(!0),u(j,null,A(d.vends.data,(e,g)=>(i(),u("tr",{key:g,class:"divide-x divide-gray-200"},[n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(d.vends.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.latestVendBinding&&e.latestVendBinding.customer?(i(),u("span",to,[y(b).includes("admin-access vends")?(i(),u("span",oo,[t("a",{class:"text-blue-700",target:"_blank",href:"//admin.happyice.com.sg/person/vend-code/"+e.code},[l(a(e.latestVendBinding.customer.code)+" ",1),no,l(" "+a(e.latestVendBinding.customer.name),1)],8,so)])):(i(),u("span",ao,[l(a(e.latestVendBinding.customer.code)+" ",1),lo,l(" "+a(e.latestVendBinding.customer.name),1)]))])):(i(),u("span",ro,a(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",io,[t("button",{type:"button",class:p(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:_=>P(e.id,1)},a(e.is_temp_error?"Error":e.temp),11,uo),t("span",co,a(e.temp_updated_at),1),e.parameterJson&&e.parameterJson.t2&&e.parameterJson.t2!=d.constTempError&&!e.is_temp_error?(i(),u("span",{key:0,class:p(["mt-1",(e.temp-e.parameterJson.t2/10).toFixed(1)>=4?"text-red-700":"text-green-700"])},a((e.temp-e.parameterJson.t2/10).toFixed(1)),3)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.vendChannelsJson?(i(),u("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:_=>X(e)},[(i(!0),u(j,null,A(e.vendChannelsJson,(_,O)=>(i(),u("li",{class:p(["quick-look",[O>0&&String(_.code)[0]!==String(e.vendChannelsJson[O-1].code)[0]?"col-start-1":""]])},[t("span",{class:p([O>0&&String(_.code)[0]!==String(e.vendChannelsJson[O-1].code)[0]?"border-t-4 pt-1":""])},[t("span",null," #"+a(_.code)+", ",1),t("span",_o,a(_.capacity-_.qty)+", ",1),t("span",{class:p([_.qty<=2?"text-red-700":"text-green-700"])},a(_.qty)+"/"+a(_.capacity),3)],2)],2))),256))],8,mo)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[(i(!0),u(j,null,A(e.vendChannelErrorLogsJson,_=>(i(),u("span",{class:p(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[_.vendChannelError?_.vendChannelError.code==4||_.vendChannelError.code==5||_.vendChannelError.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800":_.vend_channel.code==4||_.vend_channel.code==5||_.vend_channel.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800"]])},[t("div",po,[t("div",null,[l(" #"+a(_.vendChannel?_.vendChannel.code:_.vend_channel.code)+", ",1),t("span",go," ("+a(_.vendChannelError?_.vendChannelError.code:_.vend_channel_error.code)+") ",1)]),t("div",null,a(_.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),u("span",{key:0,class:p([e.vendChannelTotalsJson.balancePercent<=30?"text-red-700":e.vendChannelTotalsJson.balancePercent>60?"":"text-blue-700"])},[l(a(e.vendChannelTotalsJson.qty)+"/ "+a(e.vendChannelTotalsJson.capacity)+" ",1),ho,l(" ("+a(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),u("span",{key:0,class:p([e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-red-700":""])},[l(a(e.vendChannelTotalsJson.outOfStockSku)+"/ "+a(e.vendChannelTotalsJson.count)+" ",1),fo,l(" ("+a(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>["today_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:0,class:p([e.vendTransactionTotalsJson.today_amount/100>=30?"text-green-700":"text-red-700"])},a((e.vendTransactionTotalsJson.today_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+a(e.vendTransactionTotalsJson.today_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",3)):m("",!0),"yesterday_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:1,class:p([e.vendTransactionTotalsJson.yesterday_amount/100>=30?"text-green-700":"text-red-700"])},[yo,l(" "+a((e.vendTransactionTotalsJson.yesterday_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+a(e.vendTransactionTotalsJson.yesterday_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),"seven_days_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:2,class:p([e.vendTransactionTotalsJson.seven_days_amount/100>200?"text-green-700":"text-red-700"])},[xo,l(" "+a((e.vendTransactionTotalsJson.seven_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+a(e.vendTransactionTotalsJson.seven_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),"thirty_days_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:3,class:p([e.vendTransactionTotalsJson.thirty_days_amount/100>1e3?"text-green-700":"text-red-700"])},[bo,l(" "+a((e.vendTransactionTotalsJson.thirty_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+a(e.vendTransactionTotalsJson.thirty_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",vo,[t("div",{class:p(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",ko,[t("span",Co,a(e.is_online?"Online":"Offline"),1),e.last_updated_at?(i(),u("span",Vo,a(e.last_updated_at),1)):m("",!0)])],2),e.parameterJson?(i(),u("div",{key:0,class:p(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor%2==0?"bg-red-200":"bg-green-200"]])},[t("div",To,[So,t("span",null,a(e.parameterJson.Sensor%2==0?"Disabled":"Enabled"),1)])],2)):m("",!0),e.parameterJson&&e.parameterJson.fan?(i(),u("div",Jo,[t("div",wo,[Bo,t("span",null,a(e.parameterJson.fan),1)])])):m("",!0),e.parameterJson&&e.parameterJson.door?(i(),u("div",{key:2,class:p(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",Do,[Lo,t("span",null,a(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):m("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(i(),u("div",{key:3,class:p(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",Po,[Oo,t("span",null,a((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",$o,[e.parameterJson&&"t2"in e.parameterJson?(i(),u("button",{key:0,type:"button",class:p(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:_=>P(e.id,2)},a(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10)+"(t2) ",11,Fo)):m("",!0),e.parameterJson&&e.parameterJson.t3&&e.parameterJson.t3!=d.constTempError?(i(),u("button",{key:1,type:"button",class:p(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t3==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:_=>P(e.id,3)},a(e.parameterJson.t3==d.constTempError?"Error":e.parameterJson.t3/10)+"(t3) ",11,jo)):m("",!0),e.parameterJson&&e.parameterJson.t4&&e.parameterJson.t4!=d.constTempError?(i(),u("button",{key:2,type:"button",class:p(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t4==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:_=>P(e.id,4)},a(e.parameterJson.t4==d.constTempError?"Error":e.parameterJson.t4/10)+"(t4) ",11,Io)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?e.latestVendBinding.customer.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.parameterJson.Ver?e.parameterJson.Ver.toString(16):null)+" ",1),e.apkVerJson&&"apkver"in e.apkVerJson?(i(),u("span",Eo,[No,l("Apk: "+a(e.apkVerJson.apkver)+" ",1),e.apkVerJson&&"buildtime"in e.apkVerJson?(i(),u("span",Ko,a(y(F)(new Date(e.apkVerJson.buildtime)).format("YYMMDD HH:mm:ss")),1)):m("",!0)])):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),n(h,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Mo,[n($,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:_=>te(e)},{default:r(()=>[n(y(ge),{class:"w-4 h-4"}),Uo]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?m("",!0):(i(),u("tr",Ho,Go))])])]),d.vends.data.length?(i(),S(de,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):m("",!0)])])]),B.value?(i(),S(le,{key:0,vend:L.value,showModal:B.value,onModalClose:ee},null,8,["vend","showModal"])):m("",!0),D.value?(i(),S(re,{key:1,vend:L.value,type:W.value,showModal:D.value,permissions:y(b),onModalClose:oe},null,8,["vend","type","showModal","permissions"])):m("",!0)]}),_:1})],64))}};export{cs as default};
