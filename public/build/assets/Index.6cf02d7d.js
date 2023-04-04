import{i as y,l as A,a0 as I,j as le,o as i,g as u,a as n,b as h,w as r,F as j,H as re,d as t,P as C,c as w,p as m,t as a,m as G,f as l,J as q,a1 as ie,n as _}from"./app.63ef300e.js";import{_ as de}from"./Authenticated.634a0088.js";import{_ as F}from"./Button.7cb6ed8c.js";import ue from"./ChannelOverview.169275eb.js";import ce from"./Form.b0e797c1.js";import{_ as V,r as me,T as v,a as pe,b as f}from"./TableData.e61d2623.js";import{_ as b}from"./MultiSelect.ce46ae1e.js";import{_ as J}from"./TableHeadSort.ef9ed3be.js";import{r as _e}from"./BackspaceIcon.ddde63ea.js";import{r as ge}from"./ArrowDownTrayIcon.a43965d3.js";import{r as fe}from"./PencilSquareIcon.d96f97d4.js";import"./open-closed.fc109c14.js";import"./use-resolve-button-type.c1b18e94.js";import"./RectangleStackIcon.badbac9b.js";import"./Modal.49b44f29.js";import"./FormInput.b3481035.js";import"./ArrowUturnLeftIcon.ec111be2.js";import"./CheckCircleIcon.39a01f97.js";import"./_plugin-vue_export-helper.cdc0426e.js";const ye=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),he={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},xe={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ve={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},be=l(" Vend ID "),ke=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Ce=l(" Channel ID "),Ve=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Te=l(" Serial Num "),Je=l(" Temp >> "),Se=l(" t1-t2 Delta >> "),we=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),Be=l(" Cust ID "),Le=l(" Cust Name "),De={key:2},$e=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),Pe={key:3},Ke=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Oe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),Ie={key:4},je=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),Fe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Sensor Status ",-1),Ee=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Door Open ",-1),Ne=l(" Fan Speed << "),Me={key:5},Ue=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),He={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Ae={class:"mt-3"},Ge={class:"flex space-x-1"},qe=t("span",null," Search ",-1),Ye=t("span",null," Reset ",-1),Re={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Ze=t("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),ze=t("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Qe=[Ze,ze],We=t("span",null," Export Channels Excel ",-1),Xe={class:"flex flex-col space-y-1"},et={class:"text-sm text-gray-700 leading-5"},tt={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ot=t("span",null,"Showing",-1),st={class:"font-medium"},nt=t("span",null,"to",-1),at={class:"font-medium"},lt=t("span",null,"of",-1),rt={class:"font-medium"},it=t("span",null,"results",-1),dt={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},ut={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},ct=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Sales (Last 30 days)",-1),mt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},pt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},_t=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Avg per VM (Last 30 days)",-1),gt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},ft={class:"mt-6 flex flex-col"},yt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ht={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},xt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},vt={class:"bg-gray-100"},bt={class:"divide-x divide-gray-200"},kt=l(" # "),Ct=l(" ID "),Vt=l(" Name "),Tt=l(" Temp1(\u2103) "),Jt=t("br",null,null,-1),St=l(" \u0394t1-t2 "),wt=l(" Inventory Status "),Bt=t("br",null,null,-1),Lt=l(" (#Channel, Sales, Balance/Capacity) "),Dt=l(" Errors "),$t=l(" Balance Stock "),Pt=l(" Out of Stock SKU "),Kt=l(" $ Sales (qty)"),Ot=t("br",null,null,-1),It=l(" Today "),jt=t("br",null,null,-1),Ft=l(" Y'day"),Et=t("br",null,null,-1),Nt=l(" Last7d "),Mt=t("br",null,null,-1),Ut=l(" Last30d "),Ht=l(" Status "),At=l(" Last Visited "),Gt=l(" Temp2 "),qt=t("br",null,null,-1),Yt=l(" (Evap)"),Rt=t("br",null,null,-1),Zt=l(" \u2103 "),zt=l(" Postcode "),Qt=l(" Firmware Ver "),Wt=l(" Serial Num "),Xt={class:"bg-white"},eo={key:0},to={key:0},oo=["href"],so=t("br",null,null,-1),no={key:1},ao=t("br",null,null,-1),lo={key:1},ro={class:"flex flex-col items-center"},io=["onClick"],uo={class:"mt-1"},co=["onClick"],mo={class:"text-blue-600"},po={class:"flex flex-col"},_o={class:"font-bold"},go=t("br",null,null,-1),fo=t("br",null,null,-1),yo=t("br",null,null,-1),ho=t("br",null,null,-1),xo=t("br",null,null,-1),vo={class:"flex flex-col space-y-1"},bo={class:"flex flex-col"},ko={class:"font-bold"},Co={key:0},Vo={class:"flex flex-col"},To=t("span",{class:"font-bold"}," Drop Sensor ",-1),Jo={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},So={class:"flex flex-col"},wo=t("span",{class:"font-bold"}," Fan Speed ",-1),Bo={class:"flex flex-col"},Lo=t("span",{class:"font-bold"}," Door ",-1),Do={class:"flex flex-col"},$o=t("span",{class:"font-bold"}," Coin ",-1),Po=t("br",null,null,-1),Ko={class:"flex flex-col items-center space-y-1"},Oo=["onClick"],Io=["onClick"],jo=["onClick"],Fo={key:0,class:"text-blue-600"},Eo=t("br",null,null,-1),No={key:0},Mo={class:"flex justify-center space-x-1"},Uo=t("span",null," Edit ",-1),Ho={key:0},Ao=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Go=[Ao],cs={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,operatorOptions:Object,totals:[Array,Object],vends:Object,vendChannelErrors:Object},setup(d){const B=d,o=y({codes:"",channel_codes:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],operator:"",is_binded_customer:"",tempHigherThan:"",tempDeltaHigherThan:"",vend_channel_error_id:"",is_online:"",is_sensor:"",is_door_open:"",fanSpeedLowerThan:"",sortKey:"",sortBy:!1,numberPerPage:""}),S=y([]),Y=y([]),R=y([]),E=y([]),N=y([]),L=y(!1),M=y([]),U=y([]),D=y(!1),$=y(!1),W=y(""),P=y(),H=y([]),X=A().props.value.auth.operatorRole;A().props.value.auth.roles;const k=A().props.value.auth.permissions,Z=y(I().format("HH:mm:ss"));le(()=>{H.value=[{id:"errors_only",desc:"Errors Only"},...B.vendChannelErrors.data],M.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=H.value[0],o.value.numberPerPage=M.value[0],Y.value=B.categories.data.map(c=>({id:c.id,name:c.name})),R.value=B.categoryGroups.data.map(c=>({id:c.id,name:c.name})),S.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],N.value=[{id:"all",value:"All"},{id:"true",value:"Enabled"},{id:"false",value:"Disabled"}],E.value=[{id:"all",value:"All"},{id:"open",value:"Open"},{id:"close",value:"Close"}],U.value=[{id:"all",full_name:"All"},...B.operatorOptions.data.map(c=>({id:c.id,full_name:c.full_name}))],o.value.is_door_open=E.value[0],o.value.is_online=S.value[0],o.value.is_sensor=N.value[0],o.value.is_binded_customer=X.value?S.value[0]:S.value[1],o.value.operator=U.value[0]});function ee(c){P.value=c,D.value=!0}function te(){D.value=!1}function oe(c){P.value=c,$.value=!0}function se(){$.value=!1}function x(){q.Inertia.get("/vends",{...o.value,categories:o.value.categories.map(c=>c.id),categoryGroups:o.value.categoryGroups.map(c=>c.id),errors:o.value.errors.map(c=>c.id),operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0,onFinish:c=>{Z.value=I().format("HH:mm:ss")}})}function K(c,s){q.Inertia.get("/vends/"+c+"/temp/"+s)}function ne(){q.Inertia.get("/vends")}function T(c){o.value.sortKey=c,o.value.sortBy=!o.value.sortBy,x()}function ae(){L.value=!0,ie({method:"get",url:"/vends/channels/excel",params:{...o.value,categories:o.value.categories.map(c=>c.id),categoryGroups:o.value.categoryGroups.map(c=>c.id),errors:o.value.errors.map(c=>c.id),operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id},responseType:"blob"}).then(c=>{fileDownload(c.data,"Vending_Channels_"+I().format("YYMMDDhhmmss")+".xlsx")}).catch(c=>{console.log(c)}).finally(()=>{L.value=!1})}return(c,s)=>(i(),u(j,null,[n(h(re),{title:"Vending Machines"}),n(de,null,{header:r(()=>[ye]),default:r(()=>{var z,Q;return[t("div",he,[t("div",xe,[t("div",ve,[n(V,{placeholderStr:"Vend ID",modelValue:o.value.codes,"onUpdate:modelValue":s[0]||(s[0]=e=>o.value.codes=e),onKeyup:s[1]||(s[1]=C(e=>x(),["enter"]))},{default:r(()=>[be,ke]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Channel ID",modelValue:o.value.channel_codes,"onUpdate:modelValue":s[2]||(s[2]=e=>o.value.channel_codes=e),onKeyup:s[3]||(s[3]=C(e=>x(),["enter"]))},{default:r(()=>[Ce,Ve]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":s[4]||(s[4]=e=>o.value.serialNum=e),onKeyup:s[5]||(s[5]=C(e=>x(),["enter"]))},{default:r(()=>[Te]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":s[6]||(s[6]=e=>o.value.tempHigherThan=e),onKeyup:s[7]||(s[7]=C(e=>x(),["enter"]))},{default:r(()=>[Je]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Number",modelValue:o.value.tempDeltaHigherThan,"onUpdate:modelValue":s[8]||(s[8]=e=>o.value.tempDeltaHigherThan=e),onKeyup:s[9]||(s[9]=C(e=>x(),["enter"]))},{default:r(()=>[Se]),_:1},8,["modelValue"]),t("div",null,[we,n(b,{modelValue:o.value.errors,"onUpdate:modelValue":s[10]||(s[10]=e=>o.value.errors=e),options:H.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),h(k).includes("admin-access vends")?(i(),w(V,{key:0,placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":s[11]||(s[11]=e=>o.value.customer_code=e),onKeyup:s[12]||(s[12]=C(e=>x(),["enter"]))},{default:r(()=>[Be]),_:1},8,["modelValue"])):m("",!0),h(k).includes("admin-access vends")?(i(),w(V,{key:1,placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":s[13]||(s[13]=e=>o.value.customer_name=e),onKeyup:s[14]||(s[14]=C(e=>x(),["enter"]))},{default:r(()=>[Le]),_:1},8,["modelValue"])):m("",!0),h(k).includes("admin-access vends")?(i(),u("div",De,[$e,n(b,{modelValue:o.value.categories,"onUpdate:modelValue":s[15]||(s[15]=e=>o.value.categories=e),options:Y.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),h(k).includes("admin-access vends")?(i(),u("div",Pe,[Ke,n(b,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":s[16]||(s[16]=e=>o.value.categoryGroups=e),options:R.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[Oe,n(b,{modelValue:o.value.is_online,"onUpdate:modelValue":s[17]||(s[17]=e=>o.value.is_online=e),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),h(k).includes("admin-access vends")?(i(),u("div",Ie,[je,n(b,{modelValue:o.value.is_binded_customer,"onUpdate:modelValue":s[18]||(s[18]=e=>o.value.is_binded_customer=e),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[Fe,n(b,{modelValue:o.value.is_sensor,"onUpdate:modelValue":s[19]||(s[19]=e=>o.value.is_sensor=e),options:N.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[Ee,n(b,{modelValue:o.value.is_door_open,"onUpdate:modelValue":s[20]||(s[20]=e=>o.value.is_door_open=e),options:E.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),n(V,{placeholderStr:"Fan Speed",modelValue:o.value.fanSpeedLowerThan,"onUpdate:modelValue":s[21]||(s[21]=e=>o.value.fanSpeedLowerThan=e),onKeyup:s[22]||(s[22]=C(e=>x(),["enter"]))},{default:r(()=>[Ne]),_:1},8,["modelValue"]),h(k).includes("admin-access vends")?(i(),u("div",Me,[Ue,n(b,{modelValue:o.value.operator,"onUpdate:modelValue":s[23]||(s[23]=e=>o.value.operator=e),options:U.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0)]),t("div",He,[t("div",Ae,[t("div",Ge,[n(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[24]||(s[24]=e=>x())},{default:r(()=>[n(h(me),{class:"h-4 w-4","aria-hidden":"true"}),qe]),_:1}),n(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[25]||(s[25]=e=>ne())},{default:r(()=>[n(h(_e),{class:"h-4 w-4","aria-hidden":"true"}),Ye]),_:1}),n(F,{class:"inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[26]||(s[26]=e=>ae())},{default:r(()=>[L.value?m("",!0):(i(),w(h(ge),{key:0,class:"h-4 w-4","aria-hidden":"true"})),L.value?(i(),u("svg",Re,Qe)):m("",!0),We]),_:1})])]),t("div",Xe,[t("span",et,[t("p",null,"Last loaded: "+a(Z.value),1)]),t("p",tt,[ot,t("span",st,a((z=d.vends.meta.from)!=null?z:0),1),nt,t("span",at,a((Q=d.vends.meta.to)!=null?Q:0),1),lt,t("span",rt,a(d.vends.meta.total),1),it]),n(b,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[27]||(s[27]=e=>o.value.numberPerPage=e),options:M.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:x},null,8,["modelValue","options"])])]),t("dl",dt,[t("div",ut,[ct,t("dd",mt,a(d.totals.thirtyDays.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),t("div",pt,[_t,t("dd",gt,a((d.totals.thirtyDays/d.vends.meta.to).toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)])])]),t("div",ft,[t("div",yt,[t("div",ht,[t("table",xt,[t("thead",vt,[t("tr",bt,[n(v,null,{default:r(()=>[kt]),_:1}),n(J,{modelName:"vends.code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[28]||(s[28]=e=>T("vends.code"))},{default:r(()=>[Ct]),_:1},8,["sortKey","sortBy"]),n(v,null,{default:r(()=>[Vt]),_:1}),n(J,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[29]||(s[29]=e=>T("temp"))},{default:r(()=>[Tt,Jt,St]),_:1},8,["sortKey","sortBy"]),n(v,null,{default:r(()=>[wt,Bt,Lt]),_:1}),n(v,null,{default:r(()=>[Dt]),_:1}),n(J,{modelName:"vend_channel_totals_json->balancePercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[30]||(s[30]=e=>T("vend_channel_totals_json->balancePercent"))},{default:r(()=>[$t]),_:1},8,["sortKey","sortBy"]),n(J,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[31]||(s[31]=e=>T("vend_channel_totals_json->outOfStockSkuPercent"))},{default:r(()=>[Pt]),_:1},8,["sortKey","sortBy"]),n(J,{modelName:"vend_transaction_totals_json->thirty_days_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[32]||(s[32]=e=>T("vend_transaction_totals_json->thirty_days_amount"))},{default:r(()=>[Kt,Ot,It,jt,Ft,Et,Nt,Mt,Ut]),_:1},8,["sortKey","sortBy"]),n(v,null,{default:r(()=>[Ht]),_:1}),n(v,null,{default:r(()=>[At]),_:1}),n(J,{modelName:"parameter_json->t2",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[33]||(s[33]=e=>T("parameter_json->t2"))},{default:r(()=>[Gt,qt,Yt,Rt,Zt]),_:1},8,["sortKey","sortBy"]),n(J,{modelName:"postcode",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[34]||(s[34]=e=>T("postcode"))},{default:r(()=>[zt]),_:1},8,["sortKey","sortBy"]),n(v,null,{default:r(()=>[Qt]),_:1}),n(v,null,{default:r(()=>[Wt]),_:1}),n(v)])]),t("tbody",Xt,[(i(!0),u(j,null,G(d.vends.data,(e,g)=>(i(),u("tr",{key:g,class:"divide-x divide-gray-200"},[n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(d.vends.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.latestVendBinding&&e.latestVendBinding.customer?(i(),u("span",eo,[h(k).includes("admin-access vends")?(i(),u("span",to,[t("a",{class:"text-blue-700",target:"_blank",href:"//admin.happyice.com.sg/person/vend-code/"+e.code},[l(a(e.latestVendBinding.customer.code)+" ",1),so,l(" "+a(e.latestVendBinding.customer.name),1)],8,oo)])):(i(),u("span",no,[l(a(e.latestVendBinding.customer.code)+" ",1),ao,l(" "+a(e.latestVendBinding.customer.name),1)]))])):(i(),u("span",lo,a(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",ro,[t("button",{type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>K(e.id,1)},a(e.is_temp_error?"Error":e.temp),11,io),t("span",uo,a(e.temp_updated_at),1),e.parameterJson&&e.parameterJson.t2&&e.parameterJson.t2!=d.constTempError&&!e.is_temp_error?(i(),u("span",{key:0,class:_(["mt-1",(e.temp-e.parameterJson.t2/10).toFixed(1)>=4?"text-red-700":"text-green-700"])},a((e.temp-e.parameterJson.t2/10).toFixed(1)),3)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.vendChannelsJson?(i(),u("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:p=>ee(e)},[(i(!0),u(j,null,G(e.vendChannelsJson,(p,O)=>(i(),u("li",{class:_(["quick-look",[O>0&&String(p.code)[0]!==String(e.vendChannelsJson[O-1].code)[0]?"col-start-1":""]])},[t("span",{class:_([O>0&&String(p.code)[0]!==String(e.vendChannelsJson[O-1].code)[0]?"border-t-4 pt-1":""])},[t("span",null," #"+a(p.code)+", ",1),t("span",mo,a(p.capacity-p.qty)+", ",1),t("span",{class:_([p.qty<=2?"text-red-700":"text-green-700"])},a(p.qty)+"/"+a(p.capacity),3)],2)],2))),256))],8,co)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[(i(!0),u(j,null,G(e.vendChannelErrorLogsJson,p=>(i(),u("span",{class:_(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[p.vendChannelError?p.vendChannelError.code==4||p.vendChannelError.code==5||p.vendChannelError.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800":p.vend_channel.code==4||p.vend_channel.code==5||p.vend_channel.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800"]])},[t("div",po,[t("div",null,[l(" #"+a(p.vendChannel?p.vendChannel.code:p.vend_channel.code)+", ",1),t("span",_o," ("+a(p.vendChannelError?p.vendChannelError.code:p.vend_channel_error.code)+") ",1)]),t("div",null,a(p.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),u("span",{key:0,class:_([e.vendChannelTotalsJson.balancePercent<=30?"text-red-700":e.vendChannelTotalsJson.balancePercent>60?"":"text-blue-700"])},[l(a(e.vendChannelTotalsJson.qty)+"/ "+a(e.vendChannelTotalsJson.capacity)+" ",1),go,l(" ("+a(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),u("span",{key:0,class:_([e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-red-700":""])},[l(a(e.vendChannelTotalsJson.outOfStockSku)+"/ "+a(e.vendChannelTotalsJson.count)+" ",1),fo,l(" ("+a(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>["today_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:0,class:_([e.vendTransactionTotalsJson.today_amount/100>=30?"text-green-700":"text-red-700"])},a((e.vendTransactionTotalsJson.today_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+a(e.vendTransactionTotalsJson.today_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",3)):m("",!0),"yesterday_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:1,class:_([e.vendTransactionTotalsJson.yesterday_amount/100>=30?"text-green-700":"text-red-700"])},[yo,l(" "+a((e.vendTransactionTotalsJson.yesterday_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+a(e.vendTransactionTotalsJson.yesterday_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),"seven_days_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:2,class:_([e.vendTransactionTotalsJson.seven_days_amount/100>200?"text-green-700":"text-red-700"])},[ho,l(" "+a((e.vendTransactionTotalsJson.seven_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+a(e.vendTransactionTotalsJson.seven_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),"thirty_days_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:3,class:_([e.vendTransactionTotalsJson.thirty_days_amount/100>1e3?"text-green-700":"text-red-700"])},[xo,l(" "+a((e.vendTransactionTotalsJson.thirty_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+a(e.vendTransactionTotalsJson.thirty_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",vo,[t("div",{class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",bo,[t("span",ko,a(e.is_online?"Online":"Offline"),1),e.last_updated_at?(i(),u("span",Co,a(e.last_updated_at),1)):m("",!0)])],2),e.parameterJson?(i(),u("div",{key:0,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor%2==0?"bg-red-200":"bg-green-200"]])},[t("div",Vo,[To,t("span",null,a(e.parameterJson.Sensor%2==0?"Disabled":"Enabled"),1)])],2)):m("",!0),e.parameterJson&&"fan"in e.parameterJson?(i(),u("div",Jo,[t("div",So,[wo,t("span",null,a(e.parameterJson.fan),1)])])):m("",!0),e.parameterJson&&e.parameterJson.door?(i(),u("div",{key:2,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",Bo,[Lo,t("span",null,a(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):m("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(i(),u("div",{key:3,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",Do,[$o,t("span",null,a((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("span",null,[l(a(e.last_invoice_date)+" ",1),Po,l(" "+a(e.last_invoice_diff),1)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Ko,[e.parameterJson&&"t2"in e.parameterJson?(i(),u("button",{key:0,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>K(e.id,2)},a(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10)+"(t2) ",11,Oo)):m("",!0),e.parameterJson&&e.parameterJson.t3&&e.parameterJson.t3!=d.constTempError?(i(),u("button",{key:1,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t3==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>K(e.id,3)},a(e.parameterJson.t3==d.constTempError?"Error":e.parameterJson.t3/10)+"(t3) ",11,Io)):m("",!0),e.parameterJson&&e.parameterJson.t4&&e.parameterJson.t4!=d.constTempError?(i(),u("button",{key:2,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t4==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>K(e.id,4)},a(e.parameterJson.t4==d.constTempError?"Error":e.parameterJson.t4/10)+"(t4) ",11,jo)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?e.latestVendBinding.customer.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.parameterJson.Ver?e.parameterJson.Ver.toString(16):null)+" ",1),e.apkVerJson&&"apkver"in e.apkVerJson?(i(),u("span",Fo,[Eo,l("Apk: "+a(e.apkVerJson.apkver)+" ",1),e.apkVerJson&&"buildtime"in e.apkVerJson?(i(),u("span",No,a(h(I)(new Date(e.apkVerJson.buildtime)).format("YYMMDD HH:mm:ss")),1)):m("",!0)])):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Mo,[n(F,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:p=>oe(e)},{default:r(()=>[n(h(fe),{class:"w-4 h-4"}),Uo]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?m("",!0):(i(),u("tr",Ho,Go))])])]),d.vends.data.length?(i(),w(pe,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):m("",!0)])])]),D.value?(i(),w(ue,{key:0,vend:P.value,showModal:D.value,onModalClose:te},null,8,["vend","showModal"])):m("",!0),$.value?(i(),w(ce,{key:1,vend:P.value,type:W.value,showModal:$.value,permissions:h(k),onModalClose:se},null,8,["vend","type","showModal","permissions"])):m("",!0)]}),_:1})],64))}};export{cs as default};