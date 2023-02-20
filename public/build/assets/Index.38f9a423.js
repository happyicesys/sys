import{_ as te}from"./Authenticated.66b5912a.js";import{_ as F}from"./Button.03e0e1c6.js";import oe from"./ChannelOverview.1b132bde.js";import se from"./Form.4876fa43.js";import{_ as V,r as ne,T as x,a as le,b as f}from"./TableData.a9e3202a.js";import{_ as y}from"./MultiSelect.dc02d407.js";import{_ as C}from"./TableHeadSort.fdfe6f1a.js";import{i as h,l as ae,V as U,j as re,o as d,g as c,a as n,b,w as r,F as $,H as ie,d as t,c as J,p,t as l,m as H,f as a,J as M,n as _}from"./app.02368991.js";import{r as de}from"./BackspaceIcon.4ed8c713.js";import{r as ue}from"./PencilSquareIcon.29f3b25b.js";import"./open-closed.d2c7fa55.js";import"./use-resolve-button-type.c1945c5b.js";import"./RectangleStackIcon.83c4996c.js";import"./Modal.9a71db1b.js";import"./FormInput.f958598a.js";import"./ArrowUturnLeftIcon.cedbd963.js";import"./CheckCircleIcon.a02339c7.js";import"./_plugin-vue_export-helper.cdc0426e.js";const ce=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),me={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},pe={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},_e={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ge=a(" Vend ID "),fe=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),he=a(" Serial Num "),be=a(" Temp >> "),ye=a(" t1-t2 Delta >> "),xe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),ve=a(" Cust ID "),ke=a(" Cust Name "),Ve={key:2},Ce=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),Se={key:3},Je=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Te=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),Be={key:4},we=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),Pe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Sensor Status ",-1),Oe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Door Open ",-1),Le=a(" Fan Speed << "),$e={key:5},je=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),De={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Ne={class:"mt-3"},Ie={class:"flex space-x-1"},Ke=t("span",null," Search ",-1),Ee=t("span",null," Reset ",-1),Fe={class:"flex flex-col space-y-1"},Ue={class:"text-sm text-gray-700 leading-5"},He={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Me=t("span",null,"Showing",-1),Ae={class:"font-medium"},Ge=t("span",null,"to",-1),qe={class:"font-medium"},Re=t("span",null,"of",-1),Ye={class:"font-medium"},ze=t("span",null,"results",-1),Qe={class:"mt-6 flex flex-col"},We={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Xe={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ze={class:"min-w-full border-separate",style:{"border-spacing":"0"}},et={class:"bg-gray-100"},tt={class:"divide-x divide-gray-200"},ot=a(" # "),st=a(" ID "),nt=a(" Name "),lt=a(" Temp1(\u2103) "),at=t("br",null,null,-1),rt=a(" \u0394t1-t2 "),it=a(" Inventory Status "),dt=t("br",null,null,-1),ut=a(" (#Channel, Sales, Balance/Capacity) "),ct=a(" Errors "),mt=a(" Balance Stock "),pt=a(" Out of Stock SKU "),_t=a(" $ Sales (qty)"),gt=t("br",null,null,-1),ft=a(" Today "),ht=t("br",null,null,-1),bt=a(" Yesterday"),yt=t("br",null,null,-1),xt=a(" Last 7 Days "),vt=a(" Status "),kt=a(" Temp2 "),Vt=t("br",null,null,-1),Ct=a(" (Evap)"),St=t("br",null,null,-1),Jt=a(" \u2103 "),Tt=a(" Postcode "),Bt=a(" Firmware Ver "),wt=a(" Serial Num "),Pt={class:"bg-white"},Ot={key:0},Lt=t("br",null,null,-1),$t={key:1},jt={class:"flex flex-col items-center"},Dt=["onClick"],Nt={class:"mt-1"},It=["onClick"],Kt={class:"text-blue-600"},Et={class:"flex flex-col"},Ft={class:"font-bold"},Ut=t("br",null,null,-1),Ht=t("br",null,null,-1),Mt=t("br",null,null,-1),At=t("br",null,null,-1),Gt={class:"flex flex-col space-y-1"},qt={class:"flex flex-col"},Rt={class:"font-bold"},Yt={key:0},zt={class:"flex flex-col"},Qt=t("span",{class:"font-bold"}," Drop Sensor ",-1),Wt={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},Xt={class:"flex flex-col"},Zt=t("span",{class:"font-bold"}," Fan Speed ",-1),eo={class:"flex flex-col"},to=t("span",{class:"font-bold"}," Door ",-1),oo={class:"flex flex-col"},so=t("span",{class:"font-bold"}," Coin ",-1),no={class:"flex flex-col items-center space-y-1"},lo=["onClick"],ao=["onClick"],ro=["onClick"],io={key:0,class:"text-blue-600"},uo=t("br",null,null,-1),co={key:0},mo={class:"flex justify-center space-x-1"},po=t("span",null," Edit ",-1),_o={key:0},go=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),fo=[go],Do={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,operatorOptions:Object,vends:Object,vendChannelErrors:Object},setup(i){const T=i,o=h({codes:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],operator:"",is_binded_customer:"",tempHigherThan:"",tempDeltaHigherThan:"",vend_channel_error_id:"",is_online:"",is_sensor:"",is_door_open:"",fanSpeedLowerThan:"",sortKey:"",sortBy:!1,numberPerPage:""}),S=h([]),A=h([]),G=h([]),j=h([]),D=h([]),N=h([]),I=h([]),B=h(!1),w=h(!1),z=h(""),P=h(),K=h([]),v=ae().props.value.auth.operatorRole,q=h(U().format("HH:mm:ss"));re(()=>{K.value=[{id:"errors_only",desc:"Errors Only"},...T.vendChannelErrors.data],N.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=K.value[0],o.value.numberPerPage=N.value[0],A.value=T.categories.data.map(m=>({id:m.id,name:m.name})),G.value=T.categoryGroups.data.map(m=>({id:m.id,name:m.name})),S.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],D.value=[{id:"all",value:"All"},{id:"true",value:"Enabled"},{id:"false",value:"Disabled"}],j.value=[{id:"all",value:"All"},{id:"open",value:"Open"},{id:"close",value:"Close"}],I.value=[{id:"all",full_name:"All"},...T.operatorOptions.data.map(m=>({id:m.id,full_name:m.full_name}))],o.value.is_door_open=j.value[0],o.value.is_online=S.value[0],o.value.is_sensor=D.value[0],o.value.is_binded_customer=v.value?S.value[0]:S.value[1],o.value.operator=I.value[0]});function Q(m){P.value=m,B.value=!0}function W(){B.value=!1}function X(m){P.value=m,w.value=!0}function Z(){w.value=!1}function E(){M.Inertia.get("/vends",{...o.value,categories:o.value.categories.map(m=>m.id),categoryGroups:o.value.categoryGroups.map(m=>m.id),errors:o.value.errors.map(m=>m.id),operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0,onFinish:m=>{q.value=U().format("HH:mm:ss")}})}function O(m,s){M.Inertia.get("/vends/"+m+"/temp/"+s)}function ee(){M.Inertia.get("/vends")}function k(m){o.value.sortKey=m,o.value.sortBy=!o.value.sortBy,E()}return(m,s)=>(d(),c($,null,[n(b(ie),{title:"Vending Machines"}),n(te,null,{header:r(()=>[ce]),default:r(()=>{var R,Y;return[t("div",me,[t("div",pe,[t("div",_e,[n(V,{placeholderStr:"Vend ID",modelValue:o.value.codes,"onUpdate:modelValue":s[0]||(s[0]=e=>o.value.codes=e)},{default:r(()=>[ge,fe]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":s[1]||(s[1]=e=>o.value.serialNum=e)},{default:r(()=>[he]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":s[2]||(s[2]=e=>o.value.tempHigherThan=e)},{default:r(()=>[be]),_:1},8,["modelValue"]),n(V,{placeholderStr:"Number",modelValue:o.value.tempDeltaHigherThan,"onUpdate:modelValue":s[3]||(s[3]=e=>o.value.tempDeltaHigherThan=e)},{default:r(()=>[ye]),_:1},8,["modelValue"]),t("div",null,[xe,n(y,{modelValue:o.value.errors,"onUpdate:modelValue":s[4]||(s[4]=e=>o.value.errors=e),options:K.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),b(v)?p("",!0):(d(),J(V,{key:0,placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":s[5]||(s[5]=e=>o.value.customer_code=e)},{default:r(()=>[ve]),_:1},8,["modelValue"])),b(v)?p("",!0):(d(),J(V,{key:1,placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":s[6]||(s[6]=e=>o.value.customer_name=e)},{default:r(()=>[ke]),_:1},8,["modelValue"])),b(v)?p("",!0):(d(),c("div",Ve,[Ce,n(y,{modelValue:o.value.categories,"onUpdate:modelValue":s[7]||(s[7]=e=>o.value.categories=e),options:A.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),b(v)?p("",!0):(d(),c("div",Se,[Je,n(y,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":s[8]||(s[8]=e=>o.value.categoryGroups=e),options:G.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),t("div",null,[Te,n(y,{modelValue:o.value.is_online,"onUpdate:modelValue":s[9]||(s[9]=e=>o.value.is_online=e),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),b(v)?p("",!0):(d(),c("div",Be,[we,n(y,{modelValue:o.value.is_binded_customer,"onUpdate:modelValue":s[10]||(s[10]=e=>o.value.is_binded_customer=e),options:S.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),t("div",null,[Pe,n(y,{modelValue:o.value.is_sensor,"onUpdate:modelValue":s[11]||(s[11]=e=>o.value.is_sensor=e),options:D.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[Oe,n(y,{modelValue:o.value.is_door_open,"onUpdate:modelValue":s[12]||(s[12]=e=>o.value.is_door_open=e),options:j.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),n(V,{placeholderStr:"Fan Speed",modelValue:o.value.fanSpeedLowerThan,"onUpdate:modelValue":s[13]||(s[13]=e=>o.value.fanSpeedLowerThan=e)},{default:r(()=>[Le]),_:1},8,["modelValue"]),b(v)?p("",!0):(d(),c("div",$e,[je,n(y,{modelValue:o.value.operator,"onUpdate:modelValue":s[14]||(s[14]=e=>o.value.operator=e),options:I.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]))]),t("div",De,[t("div",Ne,[t("div",Ie,[n(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[15]||(s[15]=e=>E())},{default:r(()=>[n(b(ne),{class:"h-4 w-4","aria-hidden":"true"}),Ke]),_:1}),n(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[16]||(s[16]=e=>ee())},{default:r(()=>[n(b(de),{class:"h-4 w-4","aria-hidden":"true"}),Ee]),_:1})])]),t("div",Fe,[t("span",Ue,[t("p",null,"Last loaded: "+l(q.value),1)]),t("p",He,[Me,t("span",Ae,l((R=i.vends.meta.from)!=null?R:0),1),Ge,t("span",qe,l((Y=i.vends.meta.to)!=null?Y:0),1),Re,t("span",Ye,l(i.vends.meta.total),1),ze]),n(y,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":s[17]||(s[17]=e=>o.value.numberPerPage=e),options:N.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:E},null,8,["modelValue","options"])])])]),t("div",Qe,[t("div",We,[t("div",Xe,[t("table",Ze,[t("thead",et,[t("tr",tt,[n(x,null,{default:r(()=>[ot]),_:1}),n(C,{modelName:"vends.code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[18]||(s[18]=e=>k("vends.code"))},{default:r(()=>[st]),_:1},8,["sortKey","sortBy"]),n(x,null,{default:r(()=>[nt]),_:1}),n(C,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[19]||(s[19]=e=>k("temp"))},{default:r(()=>[lt,at,rt]),_:1},8,["sortKey","sortBy"]),n(x,null,{default:r(()=>[it,dt,ut]),_:1}),n(x,null,{default:r(()=>[ct]),_:1}),n(C,{modelName:"vend_channel_totals_json->balancePercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[20]||(s[20]=e=>k("vend_channel_totals_json->balancePercent"))},{default:r(()=>[mt]),_:1},8,["sortKey","sortBy"]),n(C,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[21]||(s[21]=e=>k("vend_channel_totals_json->outOfStockSkuPercent"))},{default:r(()=>[pt]),_:1},8,["sortKey","sortBy"]),n(C,{modelName:"vend_transaction_totals_json->seven_days_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[22]||(s[22]=e=>k("vend_transaction_totals_json->seven_days_amount"))},{default:r(()=>[_t,gt,ft,ht,bt,yt,xt]),_:1},8,["sortKey","sortBy"]),n(x,null,{default:r(()=>[vt]),_:1}),n(C,{modelName:"parameter_json->t2",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[23]||(s[23]=e=>k("parameter_json->t2"))},{default:r(()=>[kt,Vt,Ct,St,Jt]),_:1},8,["sortKey","sortBy"]),n(C,{modelName:"postcode",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:s[24]||(s[24]=e=>k("postcode"))},{default:r(()=>[Tt]),_:1},8,["sortKey","sortBy"]),n(x,null,{default:r(()=>[Bt]),_:1}),n(x,null,{default:r(()=>[wt]),_:1}),n(x)])]),t("tbody",Pt,[(d(!0),c($,null,H(i.vends.data,(e,g)=>(d(),c("tr",{key:g,class:"divide-x divide-gray-200"},[n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(i.vends.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-left"},{default:r(()=>[e.latestVendBinding&&e.latestVendBinding.customer?(d(),c("span",Ot,[a(l(e.latestVendBinding.customer.code)+" ",1),Lt,a(" "+l(e.latestVendBinding.customer.name),1)])):(d(),c("span",$t,l(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",jt,[t("button",{type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:u=>O(e.id,1)},l(e.is_temp_error?"Error":e.temp),11,Dt),t("span",Nt,l(e.temp_updated_at),1),e.parameterJson&&e.parameterJson.t2&&e.parameterJson.t2!=i.constTempError&&!e.is_temp_error?(d(),c("span",{key:0,class:_(["mt-1",(e.temp-e.parameterJson.t2/10).toFixed(1)>=4?"text-red-700":"text-green-700"])},l((e.temp-e.parameterJson.t2/10).toFixed(1)),3)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-left"},{default:r(()=>[e.vendChannelsJson?(d(),c("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:u=>Q(e)},[(d(!0),c($,null,H(e.vendChannelsJson,(u,L)=>(d(),c("li",{class:_(["quick-look",[L>0&&String(u.code)[0]!==String(e.vendChannelsJson[L-1].code)[0]?"col-start-1":""]])},[t("span",{class:_([L>0&&String(u.code)[0]!==String(e.vendChannelsJson[L-1].code)[0]?"border-t-4 pt-1":""])},[t("span",null," #"+l(u.code)+", ",1),t("span",Kt,l(u.capacity-u.qty)+", ",1),t("span",{class:_([u.qty<=2?"text-red-700":"text-green-700"])},l(u.qty)+"/"+l(u.capacity),3)],2)],2))),256))],8,It)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[(d(!0),c($,null,H(e.vendChannelErrorLogsJson,u=>(d(),c("span",{class:_(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[u.vendChannelError?u.vendChannelError.code==4||u.vendChannelError.code==5||u.vendChannelError.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800":u.vend_channel.code==4||u.vend_channel.code==5||u.vend_channel.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800"]])},[t("div",Et,[t("div",null,[a(" #"+l(u.vendChannel?u.vendChannel.code:u.vend_channel.code)+", ",1),t("span",Ft," ("+l(u.vendChannelError?u.vendChannelError.code:u.vend_channel_error.code)+") ",1)]),t("div",null,l(u.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(d(),c("span",{key:0,class:_([e.vendChannelTotalsJson.balancePercent<=30?"text-red-700":e.vendChannelTotalsJson.balancePercent>60?"":"text-blue-700"])},[a(l(e.vendChannelTotalsJson.qty)+"/ "+l(e.vendChannelTotalsJson.capacity)+" ",1),Ut,a(" ("+l(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(d(),c("span",{key:0,class:_([e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-red-700":""])},[a(l(e.vendChannelTotalsJson.outOfStockSku)+"/ "+l(e.vendChannelTotalsJson.count)+" ",1),Ht,a(" ("+l(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("span",{class:_([e.vendTransactionTotalsJson.today_amount/100>=30?"text-green-700":"text-red-700"])},[a(l((e.vendTransactionTotalsJson.today_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.today_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1),Mt],2),t("span",{class:_([e.vendTransactionTotalsJson.yesterday_amount/100>=30?"text-green-700":"text-red-700"])},[a(l((e.vendTransactionTotalsJson.yesterday_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.yesterday_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1),At],2),t("span",{class:_([e.vendTransactionTotalsJson.seven_days_amount/100>200?"text-green-700":"text-red-700"])},l((e.vendTransactionTotalsJson.seven_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+l(e.vendTransactionTotalsJson.seven_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",3)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Gt,[t("div",{class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",qt,[t("span",Rt,l(e.is_online?"Online":"Offline"),1),e.last_updated_at?(d(),c("span",Yt,l(e.last_updated_at),1)):p("",!0)])],2),e.parameterJson?(d(),c("div",{key:0,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor%2==0?"bg-red-200":"bg-green-200"]])},[t("div",zt,[Qt,t("span",null,l(e.parameterJson.Sensor%2==0?"Disabled":"Enabled"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.fan?(d(),c("div",Wt,[t("div",Xt,[Zt,t("span",null,l(e.parameterJson.fan),1)])])):p("",!0),e.parameterJson&&e.parameterJson.door?(d(),c("div",{key:2,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",eo,[to,t("span",null,l(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(d(),c("div",{key:3,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",oo,[so,t("span",null,l((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",no,[e.parameterJson&&"t2"in e.parameterJson?(d(),c("button",{key:0,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t2==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:u=>O(e.id,2)},l(e.parameterJson.t2==i.constTempError?"Error":e.parameterJson.t2/10)+"(t2) ",11,lo)):p("",!0),e.parameterJson&&e.parameterJson.t3&&e.parameterJson.t3!=i.constTempError?(d(),c("button",{key:1,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t3==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:u=>O(e.id,3)},l(e.parameterJson.t3==i.constTempError?"Error":e.parameterJson.t3/10)+"(t3) ",11,ao)):p("",!0),e.parameterJson&&e.parameterJson.t4&&e.parameterJson.t4!=i.constTempError?(d(),c("button",{key:2,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t4==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:u=>O(e.id,4)},l(e.parameterJson.t4==i.constTempError?"Error":e.parameterJson.t4/10)+"(t4) ",11,ro)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?e.latestVendBinding.customer.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.parameterJson.Ver?e.parameterJson.Ver.toString(16):null)+" ",1),e.apkVerJson&&"apkver"in e.apkVerJson?(d(),c("span",io,[uo,a("Apk: "+l(e.apkVerJson.apkver)+" ",1),e.apkVerJson&&"buildtime"in e.apkVerJson?(d(),c("span",co,l(b(U)(new Date(e.apkVerJson.buildtime)).format("YYMMDD HH:mm:ss")),1)):p("",!0)])):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),n(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",mo,[n(F,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:u=>X(e)},{default:r(()=>[n(b(ue),{class:"w-4 h-4"}),po]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),i.vends.data.length?p("",!0):(d(),c("tr",_o,fo))])])]),i.vends.data.length?(d(),J(le,{key:0,links:i.vends.links,meta:i.vends.meta},null,8,["links","meta"])):p("",!0)])])]),B.value?(d(),J(oe,{key:0,vend:P.value,showModal:B.value,onModalClose:W},null,8,["vend","showModal"])):p("",!0),w.value?(d(),J(se,{key:1,vend:P.value,type:z.value,showModal:w.value,onModalClose:Z},null,8,["vend","type","showModal"])):p("",!0)]}),_:1})],64))}};export{Do as default};
