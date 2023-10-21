import{_ as D}from"./Authenticated.51f8df2d.js";import{_ as T}from"./Button.0b5b1d9a.js";import{_ as F}from"./Paginator.0d7fb970.js";import{_,r as E}from"./SearchInput.8f48840f.js";import{_ as y}from"./MultiSelect.48ff9607.js";import{_ as g,a as u}from"./TableData.885e60f5.js";import{_ as v}from"./TableHeadSort.04d09add.js";import{g as x,h as A,f as p,a as t,u as S,w as n,F as b,o as f,Z as H,b as l,d as s,t as d,k,l as U,c as J,O as w,n as I}from"./app.8dfb483a.js";import{r as M}from"./BackspaceIcon.1cc84e35.js";import"./open-closed.cdb7e47f.js";import"./use-resolve-button-type.39e09ef6.js";import"./RectangleStackIcon.8d6abf1b.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.946a0653.js";const q=l("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),R={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},z={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},Q=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),W=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),X=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Y={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ee={class:"mt-3"},te={class:"flex space-x-1"},le=l("span",null," Search ",-1),ne=l("span",null," Reset ",-1),oe={class:"flex flex-col space-y-2"},se={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ae=l("span",null,"Showing",-1),re={class:"font-medium"},de=l("span",null,"to",-1),ie={class:"font-medium"},ue=l("span",null,"of",-1),ce={class:"font-medium"},me=l("span",null,"results",-1),ge={class:"mt-6 flex flex-col"},fe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},pe={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},_e={class:"min-w-full border-separate",style:{"border-spacing":"0"}},xe={class:"bg-gray-100"},he={class:"divide-x divide-gray-200"},ye={class:"bg-white"},ve={class:"flex flex-col"},be=["onClick"],Ve=l("br",null,null,-1),Be=l("br",null,null,-1),Ce={class:"flex flex-col"},Le={class:"font-bold"},Se={class:"grid grid-cols-[120px_minmax(120px,_1fr)_120px] gap-1"},ke={class:"font-semibold"},we={class:"text-blue-600 text-sm pl-1"},Ie={class:"pl-1"},Ne={key:0},Pe=l("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),$e=[Pe],qe={__name:"IndexLatest",props:{categories:Object,categoryGroups:Object,vends:Object,vendChannelErrors:Object},setup(r){const V=r,o=x({code:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],tempHigherThan:"",vend_channel_error_id:"",sortKey:"",sortBy:!0,numberPerPage:""}),B=x([]),C=x([]),N=x([]),P=x([]);A(()=>{B.value=[{id:"",desc:"All"},{id:"errors_only",desc:"Errors Only"},...V.vendChannelErrors.data],C.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=B.value[0],o.value.numberPerPage=C.value[0],N.value=V.categories.data.map(c=>({id:c.id,name:c.name})),P.value=V.categoryGroups.data.map(c=>({id:c.id,name:c.name}))});function L(){w.get("/vends",{...o.value,vend_channel_error_id:o.value.vend_channel_error_id.id,categories:o.value.categories.map(c=>c.id),categoryGroups:o.value.categoryGroups.map(c=>c.id),numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0})}function G(c){w.get("/vends/"+c+"/temp")}function j(){w.get("/vends")}function h(c){o.value.sortKey=c,o.value.sortBy=!this.filters.sortBy,L()}return(c,a)=>(f(),p(b,null,[t(S(H),{title:"Vending Machines"}),t(D,null,{header:n(()=>[q]),default:n(()=>{var $,K;return[l("div",R,[l("div",z,[l("div",Z,[t(_,{placeholderStr:"Code",modelValue:o.value.code,"onUpdate:modelValue":a[0]||(a[0]=e=>o.value.code=e)},{default:n(()=>[s(" Vend ID ")]),_:1},8,["modelValue"]),t(_,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":a[1]||(a[1]=e=>o.value.serialNum=e)},{default:n(()=>[s(" Serial Num ")]),_:1},8,["modelValue"]),t(_,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":a[2]||(a[2]=e=>o.value.tempHigherThan=e)},{default:n(()=>[s(" Temp >> ")]),_:1},8,["modelValue"]),l("div",null,[Q,t(y,{modelValue:o.value.vend_channel_error_id,"onUpdate:modelValue":a[3]||(a[3]=e=>o.value.vend_channel_error_id=e),options:B.value,trackBy:"id",valueProp:"id",label:"desc",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t(_,{placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":a[4]||(a[4]=e=>o.value.customer_code=e)},{default:n(()=>[s(" Cust ID ")]),_:1},8,["modelValue"]),t(_,{placeholderStr:"Cust ID Name",modelValue:o.value.customer_name,"onUpdate:modelValue":a[5]||(a[5]=e=>o.value.customer_name=e)},{default:n(()=>[s(" Cust ID Name ")]),_:1},8,["modelValue"]),l("div",null,[W,t(y,{modelValue:o.value.categories,"onUpdate:modelValue":a[6]||(a[6]=e=>o.value.categories=e),options:N.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[X,t(y,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":a[7]||(a[7]=e=>o.value.categoryGroups=e),options:P.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),l("div",Y,[l("div",ee,[l("div",te,[t(T,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[8]||(a[8]=e=>L())},{default:n(()=>[t(S(E),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(T,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[9]||(a[9]=e=>j())},{default:n(()=>[t(S(M),{class:"h-4 w-4","aria-hidden":"true"}),ne]),_:1})])]),l("div",oe,[l("p",se,[ae,l("span",re,d(($=r.vends.meta.from)!=null?$:0),1),de,l("span",ie,d((K=r.vends.meta.to)!=null?K:0),1),ue,l("span",ce,d(r.vends.meta.total),1),me]),t(y,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":a[10]||(a[10]=e=>o.value.numberPerPage=e),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:L},null,8,["modelValue","options"])])])]),l("div",ge,[l("div",fe,[l("div",pe,[l("table",_e,[l("thead",xe,[l("tr",he,[t(g,null,{default:n(()=>[s(" # ")]),_:1}),t(v,{modelName:"code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[11]||(a[11]=e=>h("code"))},{default:n(()=>[s(" Code ")]),_:1},8,["sortKey","sortBy"]),t(v,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[12]||(a[12]=e=>h("temp"))},{default:n(()=>[s(" Temp ")]),_:1},8,["sortKey","sortBy"]),t(g,null,{default:n(()=>[s(" Name ")]),_:1}),t(g,null,{default:n(()=>[s(" Category ")]),_:1}),t(g,null,{default:n(()=>[s(" Channel Status ")]),_:1}),t(g,null,{default:n(()=>[s(" Inventory Status ")]),_:1}),t(g,null,{default:n(()=>[s(" Balance Stock ")]),_:1}),t(g,null,{default:n(()=>[s(" Out of Stock SKU ")]),_:1}),t(v,{modelName:"temp_updated_at",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[13]||(a[13]=e=>h("temp_updated_at"))},{default:n(()=>[s(" Last Temp ")]),_:1},8,["sortKey","sortBy"]),t(v,{modelName:"coin_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:a[14]||(a[14]=e=>h("coin_amount"))},{default:n(()=>[s(" Coin Amount ")]),_:1},8,["sortKey","sortBy"]),t(g,null,{default:n(()=>[s(" Serial Num ")]),_:1}),t(g,null,{default:n(()=>[s(" Firmware Ver ")]),_:1}),t(g,null,{default:n(()=>[s(" Door Opening? ")]),_:1}),t(g,null,{default:n(()=>[s(" Sensor Normal? ")]),_:1})])]),l("tbody",ye,[(f(!0),p(b,null,k(r.vends.data,(e,i)=>(f(),p("tr",{key:e.id,class:"divide-x divide-gray-200"},[t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[s(d(r.vends.meta.from+i),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[s(d(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[l("div",ve,[l("button",{type:"button",class:I(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:m=>G(e.id)},d(e.is_temp_error?"Error":e.temp),11,be)])]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-left"},{default:n(()=>[s(d(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.code:null)+" ",1),Ve,s(" "+d(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-left"},{default:n(()=>[s(d(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category?e.latestVendBinding.customer.category.name:null)+" ",1),Be,s(" "+d(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category&&e.latestVendBinding.customer.category.category_group?e.latestVendBinding.customer.category.category_group.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[(f(!0),p(b,null,k(e.vendChannelErrorLogsJson,m=>(f(),p("span",{class:I(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[m.is_error_cleared?"bg-green-100 text-green-800":"bg-red-100 text-red-800"]])},[l("div",Ce,[l("div",null,[s(" #"+d(m.vend_channel.code)+", ",1),l("span",Le," ("+d(m.vend_channel_error.code)+") ",1)]),l("div",null,d(m.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[l("div",Se,[(f(!0),p(b,null,k(e.vendChannelsJson,(m,O)=>(f(),p("div",{class:I(["inline-flex justify-between items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full",[O>0&&String(m.code)[0]!==String(e.vendChannelsJson[O-1].code)[0]?"col-start-1":""]])},[l("div",ke," #"+d(m.code)+", ",1),l("div",we,d(m.capacity-m.qty)+", ",1),l("div",Ie,d(m.qty)+"/"+d(m.capacity),1)],2))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[s(d(e.temp_updated_at),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-right"},{default:n(()=>[s(d(e.coin_amount),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[s(d(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[s(d(e.firmware_ver),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[s(d(e.is_door_open),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:r.vends.length,inputClass:"text-center"},{default:n(()=>[s(d(e.is_sensor_normal),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),r.vends.data.length?U("",!0):(f(),p("tr",Ne,$e))])]),r.vends.data.length?(f(),J(F,{key:0,links:r.vends.links,meta:r.vends.meta},null,8,["links","meta"])):U("",!0)])])])])]}),_:1})],64))}};export{qe as default};
