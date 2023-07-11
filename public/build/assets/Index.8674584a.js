import{h as f,K as W,V as N,j as re,f as c,a as s,u as v,w as r,F as j,o as d,Z as ie,b as t,P as b,d as l,c as J,m,t as a,l as A,O as R,ab as de,n as g}from"./app.09198d16.js";import{_ as ue}from"./Authenticated.e87f29e9.js";import{_ as F}from"./Button.55a361b3.js";import ce from"./ChannelOverview.2412d8ab.js";import me from"./Form.cefdc1db.js";import{_ as V,a as pe,b as y}from"./TableData.071ca813.js";import{_ as h,r as _e}from"./SearchInput.610e202d.js";import{_ as C}from"./MultiSelect.20a8799f.js";import{_ as T}from"./TableHeadSort.d087aabb.js";import{r as ge}from"./BackspaceIcon.54694c4a.js";import{r as ye}from"./ArrowDownTrayIcon.43479051.js";import{r as fe}from"./PencilSquareIcon.522896c9.js";import"./open-closed.153f939f.js";import"./use-resolve-button-type.831aaa0f.js";import"./RectangleStackIcon.9284777a.js";import"./Modal.f2e318ff.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.78577212.js";import"./FormInput.55b40d13.js";import"./ArrowUturnLeftIcon.c908db2b.js";const ve=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),xe={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},be={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},he={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ke=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Ce=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Te=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),Se={key:2},Ve=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),Je={key:3},we=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),Be={key:4},Ke=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),Oe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Sensor Status ",-1),$e=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Door Open ",-1),De={key:5},Pe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),Ne=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),je={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Fe={class:"mt-3"},Ie={class:"flex space-x-1"},Ee=t("span",null," Search ",-1),Ue=t("span",null," Reset ",-1),Me={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Ge=t("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),He=t("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Ae=[Ge,He],Re=t("span",null," Export Channels Excel ",-1),qe={class:"flex flex-col space-y-1"},Ye={class:"text-sm text-gray-700 leading-5"},Ze={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ze=t("span",null,"Showing",-1),Qe={class:"font-medium"},We=t("span",null,"to",-1),Xe={class:"font-medium"},et=t("span",null,"of",-1),tt={class:"font-medium"},ot=t("span",null,"results",-1),nt={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},st={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},lt=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Sales (Last 30 days)",-1),at={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},rt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},it=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Avg per VM (Last 30 days)",-1),dt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},ut={class:"mt-6 flex flex-col"},ct={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},mt={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},pt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},_t={class:"bg-gray-100"},gt={class:"divide-x divide-gray-200"},yt=t("br",null,null,-1),ft=t("br",null,null,-1),vt=t("br",null,null,-1),xt=t("br",null,null,-1),bt=t("br",null,null,-1),ht=t("br",null,null,-1),kt=t("br",null,null,-1),Ct=t("br",null,null,-1),Tt={class:"bg-white"},St={key:0},Vt={key:0},Jt=["href"],wt=t("br",null,null,-1),Lt={key:1},Bt=t("br",null,null,-1),Kt={key:1},Ot={class:"flex flex-col items-center"},$t=["onClick"],Dt={class:"mt-1"},Pt=["onClick"],Nt={class:"text-blue-600"},jt={class:"flex flex-col"},Ft={class:"font-bold"},It=t("br",null,null,-1),Et=t("br",null,null,-1),Ut=t("br",null,null,-1),Mt=t("br",null,null,-1),Gt=t("br",null,null,-1),Ht={class:"flex flex-col space-y-1"},At={class:"flex flex-col"},Rt={class:"font-bold"},qt={key:0},Yt={class:"flex flex-col"},Zt=t("span",{class:"font-bold"}," Drop Sensor ",-1),zt={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},Qt={class:"flex flex-col"},Wt=t("span",{class:"font-bold"}," Fan Speed ",-1),Xt={class:"flex flex-col"},eo=t("span",{class:"font-bold"}," Door ",-1),to={class:"flex flex-col"},oo=t("span",{class:"font-bold"}," Coin ",-1),no={key:0},so=t("br",null,null,-1),lo=t("br",null,null,-1),ao=t("br",null,null,-1),ro={class:"flex flex-col items-center space-y-1"},io=["onClick"],uo=["onClick"],co=["onClick"],mo={key:0,class:"text-blue-600"},po=t("br",null,null,-1),_o={key:0},go={class:"flex justify-center space-x-1"},yo=t("span",null," Edit ",-1),fo={key:0},vo=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),xo=[vo],Io={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,locationTypeOptions:Object,operatorOptions:Object,productOptions:Object,totals:[Array,Object],vends:Object,vendChannelErrors:Object},setup(i){const L=i,o=f({codes:"",channel_codes:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],locationType:"",operator:"",is_binded_customer:"",tempHigherThan:"",tempDeltaHigherThan:"",vend_channel_error_id:"",lastVisitedGreaterThan:"",is_online:"",is_sensor:"",is_door_open:"",fanSpeedLowerThan:"",balanceStockLessThan:"",remainingSkuLessThan:"",sortKey:"",sortBy:!1,numberPerPage:"",visited:!0}),w=f([]),q=f([]),Y=f([]),I=f([]),E=f([]),B=f(!1),U=f([]),M=f([]),G=f([]),K=f(!1),O=f(!1),X=f(""),$=f(),H=f([]),ee=W().props.auth.operatorRole,S=W().props.auth.permissions,Z=f(N().format("HH:mm:ss"));re(()=>{o.value.visited=!0,H.value=[{id:"errors_only",desc:"Errors Only"},...L.vendChannelErrors.data],M.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=H.value[0],o.value.numberPerPage=M.value[0],q.value=L.categories.data.map(u=>({id:u.id,name:u.name})),Y.value=L.categoryGroups.data.map(u=>({id:u.id,name:u.name})),w.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],E.value=[{id:"all",value:"All"},{id:"true",value:"Enabled"},{id:"false",value:"Disabled"}],I.value=[{id:"all",value:"All"},{id:"open",value:"Open"},{id:"close",value:"Close"}],U.value=[{id:"all",value:"All"},...L.locationTypeOptions.data.map(u=>({id:u.id,value:u.name}))],G.value=[{id:"all",full_name:"All"},...L.operatorOptions.data.map(u=>({id:u.id,full_name:u.full_name}))],o.value.is_door_open=I.value[0],o.value.is_online=w.value[0],o.value.is_sensor=E.value[0],o.value.is_binded_customer=ee.value?w.value[0]:w.value[1],o.value.locationType=U.value[0],o.value.operator=G.value[0]});function te(u){$.value=u,K.value=!0}function oe(){K.value=!1}function ne(u){$.value=u,O.value=!0}function se(){O.value=!1}function x(){R.get("/vends",{...o.value,categories:o.value.categories.map(u=>u.id),categoryGroups:o.value.categoryGroups.map(u=>u.id),errors:o.value.errors.map(u=>u.id),location_type_id:o.value.locationType.id,operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0,onFinish:u=>{Z.value=N().format("HH:mm:ss")}})}function D(u,n){R.get("/vends/"+u+"/temp/"+n)}function le(){R.get("/vends")}function k(u){o.value.sortKey=u,o.value.sortBy=!o.value.sortBy,x()}function ae(){B.value=!0,de({method:"get",url:"/vends/channels/excel",params:{...o.value,categories:o.value.categories.map(u=>u.id),categoryGroups:o.value.categoryGroups.map(u=>u.id),errors:o.value.errors.map(u=>u.id),location_type_id:o.value.locationType.id,operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id},responseType:"blob"}).then(u=>{fileDownload(u.data,"Vending_Channels_"+N().format("YYMMDDhhmmss")+".xlsx")}).catch(u=>{console.log(u)}).finally(()=>{B.value=!1})}return(u,n)=>(d(),c(j,null,[s(v(ie),{title:"Vending Machines"}),s(ue,null,{header:r(()=>[ve]),default:r(()=>{var z,Q;return[t("div",xe,[t("div",be,[t("div",he,[s(h,{placeholderStr:"Vend ID",modelValue:o.value.codes,"onUpdate:modelValue":n[0]||(n[0]=e=>o.value.codes=e),onKeyup:n[1]||(n[1]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Vend ID "),ke]),_:1},8,["modelValue"]),s(h,{placeholderStr:"Channel ID",modelValue:o.value.channel_codes,"onUpdate:modelValue":n[2]||(n[2]=e=>o.value.channel_codes=e),onKeyup:n[3]||(n[3]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Channel ID "),Ce]),_:1},8,["modelValue"]),s(h,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":n[4]||(n[4]=e=>o.value.serialNum=e),onKeyup:n[5]||(n[5]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Serial Num ")]),_:1},8,["modelValue"]),s(h,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":n[6]||(n[6]=e=>o.value.tempHigherThan=e),onKeyup:n[7]||(n[7]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Temp >> ")]),_:1},8,["modelValue"]),s(h,{placeholderStr:"Number",modelValue:o.value.tempDeltaHigherThan,"onUpdate:modelValue":n[8]||(n[8]=e=>o.value.tempDeltaHigherThan=e),onKeyup:n[9]||(n[9]=b(e=>x(),["enter"]))},{default:r(()=>[l(" t1-t2 Delta >> ")]),_:1},8,["modelValue"]),t("div",null,[Te,s(C,{modelValue:o.value.errors,"onUpdate:modelValue":n[10]||(n[10]=e=>o.value.errors=e),options:H.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),v(S).includes("admin-access vends")?(d(),J(h,{key:0,placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":n[11]||(n[11]=e=>o.value.customer_code=e),onKeyup:n[12]||(n[12]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Cust ID ")]),_:1},8,["modelValue"])):m("",!0),v(S).includes("admin-access vends")?(d(),J(h,{key:1,placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":n[13]||(n[13]=e=>o.value.customer_name=e),onKeyup:n[14]||(n[14]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Cust Name ")]),_:1},8,["modelValue"])):m("",!0),v(S).includes("admin-access vends")?(d(),c("div",Se,[Ve,s(C,{modelValue:o.value.categories,"onUpdate:modelValue":n[15]||(n[15]=e=>o.value.categories=e),options:q.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),v(S).includes("admin-access vends")?(d(),c("div",Je,[we,s(C,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":n[16]||(n[16]=e=>o.value.categoryGroups=e),options:Y.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[Le,s(C,{modelValue:o.value.is_online,"onUpdate:modelValue":n[17]||(n[17]=e=>o.value.is_online=e),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),v(S).includes("admin-access vends")?(d(),c("div",Be,[Ke,s(C,{modelValue:o.value.is_binded_customer,"onUpdate:modelValue":n[18]||(n[18]=e=>o.value.is_binded_customer=e),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[Oe,s(C,{modelValue:o.value.is_sensor,"onUpdate:modelValue":n[19]||(n[19]=e=>o.value.is_sensor=e),options:E.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[$e,s(C,{modelValue:o.value.is_door_open,"onUpdate:modelValue":n[20]||(n[20]=e=>o.value.is_door_open=e),options:I.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),s(h,{placeholderStr:"Fan Speed",modelValue:o.value.fanSpeedLowerThan,"onUpdate:modelValue":n[21]||(n[21]=e=>o.value.fanSpeedLowerThan=e),onKeyup:n[22]||(n[22]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Fan Speed << ")]),_:1},8,["modelValue"]),v(S).includes("admin-access vends")?(d(),c("div",De,[Pe,s(C,{modelValue:o.value.operator,"onUpdate:modelValue":n[23]||(n[23]=e=>o.value.operator=e),options:G.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[Ne,s(C,{modelValue:o.value.locationType,"onUpdate:modelValue":n[24]||(n[24]=e=>o.value.locationType=e),options:U.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),s(h,{placeholderStr:"How many Day(s)",modelValue:o.value.lastVisitedGreaterThan,"onUpdate:modelValue":n[25]||(n[25]=e=>o.value.lastVisitedGreaterThan=e),onKeyup:n[26]||(n[26]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Last Visited Day >> ")]),_:1},8,["modelValue"]),s(h,{placeholderStr:"Balance Stock Less Than",modelValue:o.value.balanceStockLessThan,"onUpdate:modelValue":n[27]||(n[27]=e=>o.value.balanceStockLessThan=e),onKeyup:n[28]||(n[28]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Balance Stock(%) << ")]),_:1},8,["modelValue"]),s(h,{placeholderStr:"Remaining SKU Less Than",modelValue:o.value.remainingSkuLessThan,"onUpdate:modelValue":n[29]||(n[29]=e=>o.value.remainingSkuLessThan=e),onKeyup:n[30]||(n[30]=b(e=>x(),["enter"]))},{default:r(()=>[l(" Remaining SKU(%) << ")]),_:1},8,["modelValue"])]),t("div",je,[t("div",Fe,[t("div",Ie,[s(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[31]||(n[31]=e=>x())},{default:r(()=>[s(v(_e),{class:"h-4 w-4","aria-hidden":"true"}),Ee]),_:1}),s(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[32]||(n[32]=e=>le())},{default:r(()=>[s(v(ge),{class:"h-4 w-4","aria-hidden":"true"}),Ue]),_:1}),v(S).includes("export excel")?(d(),J(F,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-gray-600 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[33]||(n[33]=e=>ae())},{default:r(()=>[B.value?m("",!0):(d(),J(v(ye),{key:0,class:"h-4 w-4","aria-hidden":"true"})),B.value?(d(),c("svg",Me,Ae)):m("",!0),Re]),_:1})):m("",!0)])]),t("div",qe,[t("span",Ye,[t("p",null,"Last loaded: "+a(Z.value),1)]),t("p",Ze,[ze,t("span",Qe,a((z=i.vends.meta.from)!=null?z:0),1),We,t("span",Xe,a((Q=i.vends.meta.to)!=null?Q:0),1),et,t("span",tt,a(i.vends.meta.total),1),ot]),s(C,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":n[34]||(n[34]=e=>o.value.numberPerPage=e),options:M.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:x},null,8,["modelValue","options"])])]),t("dl",nt,[t("div",st,[lt,t("dd",at,a(i.totals.thirtyDays.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),t("div",rt,[it,t("dd",dt,a((i.totals.thirtyDays/i.vends.meta.to?i.totals.thirtyDays/i.vends.meta.to:0).toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)])])]),t("div",ut,[t("div",ct,[t("div",mt,[t("table",pt,[t("thead",_t,[t("tr",gt,[s(V,null,{default:r(()=>[l(" # ")]),_:1}),s(T,{modelName:"vends.code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[35]||(n[35]=e=>k("vends.code"))},{default:r(()=>[l(" ID ")]),_:1},8,["sortKey","sortBy"]),s(V,null,{default:r(()=>[l(" Name ")]),_:1}),s(T,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[36]||(n[36]=e=>k("temp"))},{default:r(()=>[l(" Temp1(\u2103) "),yt,l(" \u0394t1-t2 ")]),_:1},8,["sortKey","sortBy"]),s(V,null,{default:r(()=>[l(" Inventory Status "),ft,l(" (#Channel, Sold, Balance/Capacity) ")]),_:1}),s(V,null,{default:r(()=>[l(" Errors ")]),_:1}),s(T,{modelName:"vend_channel_totals_json->balancePercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[37]||(n[37]=e=>k("vend_channel_totals_json->balancePercent"))},{default:r(()=>[l(" Balance Stock ")]),_:1},8,["sortKey","sortBy"]),s(T,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[38]||(n[38]=e=>k("vend_channel_totals_json->outOfStockSkuPercent"))},{default:r(()=>[l(" Remaining SKU# ")]),_:1},8,["sortKey","sortBy"]),s(T,{modelName:"vend_transaction_totals_json->thirty_days_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[39]||(n[39]=e=>k("vend_transaction_totals_json->thirty_days_amount"))},{default:r(()=>[l(" $ Sales (qty)"),vt,l(" Today "),xt,l(" Y'day"),bt,l(" Last7d "),ht,l(" Last30d ")]),_:1},8,["sortKey","sortBy"]),s(V,null,{default:r(()=>[l(" Status ")]),_:1}),s(T,{modelName:"last_invoice_date",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[40]||(n[40]=e=>k("last_invoice_date"))},{default:r(()=>[l(" Last Visited ")]),_:1},8,["sortKey","sortBy"]),s(T,{modelName:"next_invoice_date",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[41]||(n[41]=e=>k("next_invoice_date"))},{default:r(()=>[l(" Next Planned Visit ")]),_:1},8,["sortKey","sortBy"]),s(T,{modelName:"parameter_json->t2",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[42]||(n[42]=e=>k("parameter_json->t2"))},{default:r(()=>[l(" Temp2 "),kt,l(" (Evap)"),Ct,l(" \u2103 ")]),_:1},8,["sortKey","sortBy"]),s(T,{modelName:"postcode",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[43]||(n[43]=e=>k("postcode"))},{default:r(()=>[l(" Postcode ")]),_:1},8,["sortKey","sortBy"]),s(V,null,{default:r(()=>[l(" Firmware Ver ")]),_:1}),s(V,null,{default:r(()=>[l(" Serial Num ")]),_:1}),s(T,{modelName:"location_type_name",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[44]||(n[44]=e=>k("location_type_name"))},{default:r(()=>[l(" Location ")]),_:1},8,["sortKey","sortBy"]),s(V)])]),t("tbody",Tt,[(d(!0),c(j,null,A(i.vends.data,(e,_)=>(d(),c("tr",{key:_,class:"divide-x divide-gray-200"},[s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(i.vends.meta.from+_),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-left"},{default:r(()=>[e.customer_code?(d(),c("span",St,[v(S).includes("admin-access vends")?(d(),c("span",Vt,[t("a",{class:"text-blue-700",target:"_blank",href:"//admin.happyice.com.sg/person/vend-code/"+e.code},[l(a(e.customer_code)+" ",1),wt,l(" "+a(e.customer_name),1)],8,Jt)])):(d(),c("span",Lt,[l(a(e.customer_code)+" ",1),Bt,l(" "+a(e.customer_name),1)]))])):(d(),c("span",Kt,a(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Ot,[t("button",{type:"button",class:g(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>D(e.id,1)},a(e.is_temp_error?"Error":e.temp),11,$t),t("span",Dt,a(e.temp_updated_at),1),e.parameterJson&&e.parameterJson.t2&&e.parameterJson.t2!=i.constTempError&&!e.is_temp_error?(d(),c("span",{key:0,class:g(["mt-1",(e.temp-e.parameterJson.t2/10).toFixed(1)>=4?"text-red-700":"text-green-700"])},a((e.temp-e.parameterJson.t2/10).toFixed(1)),3)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-left"},{default:r(()=>[e.vendChannelsJson?(d(),c("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:p=>te(e)},[(d(!0),c(j,null,A(e.vendChannelsJson,(p,P)=>(d(),c("li",{class:g(["quick-look",[P>0&&String(p.code)[0]!==String(e.vendChannelsJson[P-1].code)[0]?"col-start-1":""]])},[t("span",{class:g([P>0&&String(p.code)[0]!==String(e.vendChannelsJson[P-1].code)[0]?"border-t-4 pt-1":""])},[t("span",null," #"+a(p.code)+", ",1),t("span",Nt,a(p.capacity-p.qty)+", ",1),t("span",{class:g([p.qty<=2?"text-red-700":"text-green-700"])},a(p.qty)+"/"+a(p.capacity),3)],2)],2))),256))],8,Pt)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[(d(!0),c(j,null,A(e.vendChannelErrorLogsJson,p=>(d(),c("span",{class:g(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[p.vendChannelError?p.vendChannelError.code==4||p.vendChannelError.code==5||p.vendChannelError.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800":p.vend_channel.code==4||p.vend_channel.code==5||p.vend_channel.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800"]])},[t("div",jt,[t("div",null,[l(" #"+a(p.vendChannel?p.vendChannel.code:p.vend_channel.code)+", ",1),t("span",Ft," ("+a(p.vendChannelError?p.vendChannelError.code:p.vend_channel_error.code)+") ",1)]),t("div",null,a(p.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(d(),c("span",{key:0,class:g([e.vendChannelTotalsJson.balancePercent<=15?"text-red-700":e.vendChannelTotalsJson.balancePercent>40?"text-green-700":"text-blue-700"])},[l(a(e.vendChannelTotalsJson.qty)+"/ "+a(e.vendChannelTotalsJson.capacity)+" ",1),It,l(" ("+a(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(d(),c("span",{key:0,class:g([100-e.vendChannelTotalsJson.outOfStockSkuPercent<=25?"text-red-700":100-e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-green-700":"text-blue-700"])},[l(a(e.vendChannelTotalsJson.count-e.vendChannelTotalsJson.outOfStockSku)+"/ "+a(e.vendChannelTotalsJson.count)+" ",1),Et,l(" ("+a(100-e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>["today_amount"in e.vendTransactionTotalsJson?(d(),c("span",{key:0,class:g([e.vendTransactionTotalsJson.today_amount/100>=30?"text-green-700":"text-red-700"])},a((e.vendTransactionTotalsJson.today_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+a(e.vendTransactionTotalsJson.today_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",3)):m("",!0),"yesterday_amount"in e.vendTransactionTotalsJson?(d(),c("span",{key:1,class:g([e.vendTransactionTotalsJson.yesterday_amount/100>=30?"text-green-700":"text-red-700"])},[Ut,l(" "+a((e.vendTransactionTotalsJson.yesterday_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+a(e.vendTransactionTotalsJson.yesterday_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),"seven_days_amount"in e.vendTransactionTotalsJson?(d(),c("span",{key:2,class:g([e.vendTransactionTotalsJson.seven_days_amount/100>200?"text-green-700":"text-red-700"])},[Mt,l(" "+a((e.vendTransactionTotalsJson.seven_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+a(e.vendTransactionTotalsJson.seven_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),"thirty_days_amount"in e.vendTransactionTotalsJson?(d(),c("span",{key:3,class:g([e.vendTransactionTotalsJson.thirty_days_amount/100>1e3?"text-green-700":"text-red-700"])},[Gt,l(" "+a((e.vendTransactionTotalsJson.thirty_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+a(e.vendTransactionTotalsJson.thirty_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Ht,[t("div",{class:g(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",At,[t("span",Rt,a(e.is_online?"Online":"Offline"),1),e.last_updated_at?(d(),c("span",qt,a(e.last_updated_at),1)):m("",!0)])],2),e.parameterJson?(d(),c("div",{key:0,class:g(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor%2==0?"bg-red-200":"bg-green-200"]])},[t("div",Yt,[Zt,t("span",null,a(e.parameterJson.Sensor%2==0?"Disabled":"Enabled"),1)])],2)):m("",!0),e.parameterJson&&"fan"in e.parameterJson?(d(),c("div",zt,[t("div",Qt,[Wt,t("span",null,a(e.parameterJson.fan),1)])])):m("",!0),e.parameterJson&&e.parameterJson.door?(d(),c("div",{key:2,class:g(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",Xt,[eo,t("span",null,a(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):m("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(d(),c("div",{key:3,class:g(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",to,[oo,t("span",null,a((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[e.cms_invoice_history&&"last_delivery_driver"in e.cms_invoice_history?(d(),c("span",no,[l(a(e.cms_invoice_history.last_delivery_driver)+" ",1),so])):m("",!0),t("span",null,[l(a(e.last_invoice_date)+" ",1),lo,l(" "+a(e.last_invoice_diff),1)])]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("span",null,[l(a(e.next_invoice_date)+" ",1),ao,l(" "+a(e.next_invoice_diff),1)])]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",ro,[e.parameterJson&&"t2"in e.parameterJson?(d(),c("button",{key:0,type:"button",class:g(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t2==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>D(e.id,2)},a(e.parameterJson.t2==i.constTempError?"Error":e.parameterJson.t2/10)+"(t2) ",11,io)):m("",!0),e.parameterJson&&e.parameterJson.t3&&e.parameterJson.t3!=i.constTempError?(d(),c("button",{key:1,type:"button",class:g(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t3==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>D(e.id,3)},a(e.parameterJson.t3==i.constTempError?"Error":e.parameterJson.t3/10)+"(t3) ",11,uo)):m("",!0),e.parameterJson&&e.parameterJson.t4&&e.parameterJson.t4!=i.constTempError?(d(),c("button",{key:2,type:"button",class:g(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t4==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>D(e.id,4)},a(e.parameterJson.t4==i.constTempError?"Error":e.parameterJson.t4/10)+"(t4) ",11,co)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.postcode),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.parameterJson&&e.parameterJson.Ver?e.parameterJson.Ver.toString(16):null)+" ",1),e.apkVerJson&&"apkver"in e.apkVerJson?(d(),c("span",mo,[po,l("Apk: "+a(e.apkVerJson.apkver)+" ",1),e.apkVerJson&&"buildtime"in e.apkVerJson?(d(),c("span",_o,a(v(N)(new Date(e.apkVerJson.buildtime)).format("YYMMDD HH:mm:ss")),1)):m("",!0)])):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[l(a(e.location_type_name),1)]),_:2},1032,["currentIndex","totalLength"]),s(y,{currentIndex:_,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",go,[s(F,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:p=>ne(e)},{default:r(()=>[s(v(fe),{class:"w-4 h-4"}),yo]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),i.vends.data.length?m("",!0):(d(),c("tr",fo,xo))])])]),i.vends.data.length?(d(),J(pe,{key:0,links:i.vends.links,meta:i.vends.meta},null,8,["links","meta"])):m("",!0)])])]),K.value?(d(),J(ce,{key:0,productOptions:i.productOptions,vend:$.value,showModal:K.value,onModalClose:oe},null,8,["productOptions","vend","showModal"])):m("",!0),O.value?(d(),J(me,{key:1,vend:$.value,type:X.value,showModal:O.value,permissions:v(S),onModalClose:se},null,8,["vend","type","showModal","permissions"])):m("",!0)]}),_:1})],64))}};export{Io as default};
